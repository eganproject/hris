<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Http\Requests\ScheduleAssignmentRequest;
use App\Http\Requests\ScheduleOverrideRequest;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Services\ScheduleGenerator;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Carbon\CarbonPeriod;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleGenerator $generator)
    {
    }

    /**
     * Monthly roster grid: employees × days, showing the materialized schedule.
     */
    public function index(Request $request): View
    {
        $month = $this->resolveMonth($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $jobPositionId = $request->integer('job_position_id') ?: null;
        $search = $request->string('search')->toString();
        $scope = DataScope::forAttendance($request->user());

        $applyFilters = fn ($query) => $query
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->byDepartment($departmentId))
            ->when($jobPositionId, fn ($q) => $q->where('job_position_id', $jobPositionId))
            ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$s}%")->orWhere('employee_number', 'like', "%{$s}%")));

        $employees = $applyFilters($scope->employees()->active())
            ->with(['schedules' => fn ($query) => $query
                ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                ->with('shift'),
            ])
            ->orderBy('full_name')
            ->get();

        // National holidays overlay the grid so users see why a day may be off.
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $branchId))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        $days = collect(CarbonPeriod::create($from, $to)->toArray());

        $assignments = ScheduleAssignment::query()
            ->with(['employee', 'pattern'])
            ->overlapping($from, $to)
            ->when($branchId, fn ($query) => $query->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId)))
            ->tap(fn ($query) => $scope->constrain($query))
            ->visibleToCreator($request->user())
            ->orderByDesc('start_date')
            ->get();

        return view('attendance.schedules.index', [
            'employees' => $employees,
            'days' => $days,
            'holidays' => $holidays,
            'leaves' => $this->approvedLeaveByDate($from, $to, $branchId, null, $scope),
            'assignments' => $assignments,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'jobPositions' => JobPosition::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => ['branch_id' => $branchId, 'department_id' => $departmentId, 'job_position_id' => $jobPositionId, 'search' => $search],
            'branchId' => $branchId,
            'hasNoScope' => $scope->isEmpty(),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
            'patternCount' => SchedulePattern::query()->visibleTo($request->user())->where('is_active', true)->count(),
        ]);
    }

    /**
     * One employee's month: every day with its shift, the source (pola vs manual),
     * public holidays and approved leave — the "why is this person not working"
     * view that the roster grid can only hint at.
     */
    public function show(Request $request, Employee $employee): View
    {
        DataScope::forAttendance($request->user())->authorize($employee);

        $month = $this->resolveMonth($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $employee->load(['branch', 'department', 'jobPosition']);

        $schedules = $employee->schedules()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->with('shift')
            ->get()
            ->keyBy(fn ($schedule) => $schedule->work_date->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $employee->branch_id))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        $leaves = $this->approvedLeaveByDate($from, $to, null, $employee)->get($employee->id, collect());

        $days = collect(CarbonPeriod::create($from, $to)->toArray());

        return view('attendance.schedules.employee', [
            'employee' => $employee,
            'days' => $days,
            'schedules' => $schedules,
            'holidays' => $holidays,
            'leaves' => $leaves,
            'assignments' => $employee->scheduleAssignments()
                ->with('pattern')
                ->orderByDesc('start_date')
                ->get(),
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    /**
     * Approved leave expanded per day, so a roster cell can answer "is this person
     * off on leave today?" with a single lookup.
     *
     * @return Collection<int, Collection<string, LeaveRequest>>  employee id => date => leave
     */
    private function approvedLeaveByDate(Carbon $from, Carbon $to, ?int $branchId = null, ?Employee $employee = null, ?DataScope $scope = null): Collection
    {
        $leaves = LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->when($employee, fn ($query) => $query->where('employee_id', $employee->id))
            ->when($branchId, fn ($query) => $query->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId)))
            ->when($scope, fn ($query) => $scope->constrain($query))
            ->with('leaveType')
            ->get();

        $byEmployee = [];

        foreach ($leaves as $leave) {
            $start = $leave->start_date->greaterThan($from) ? $leave->start_date->copy() : $from->copy();
            $end = $leave->end_date->lessThan($to) ? $leave->end_date->copy() : $to->copy();

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $byEmployee[$leave->employee_id][$day->toDateString()] = $leave;
            }
        }

        return collect($byEmployee)->map(fn (array $days) => collect($days));
    }

    public function create(Request $request): View
    {
        // The picker shows each employee's still-running/upcoming assignments so the
        // user can see which periods are already taken before choosing new dates.
        $employees = DataScope::forAttendance($request->user())
            ->employees()
            ->active()
            ->with([
                'jobPosition:id,name',
                'department:id,name',
                'branch:id,name',
                'scheduleAssignments' => fn ($query) => $query
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))
                    ->with('pattern:id,name,type')
                    ->orderBy('start_date'),
            ])
            ->orderBy('full_name')
            ->get();

        return view('attendance.schedules.assign', [
            'employees' => $employees,
            // Hanya pola milik pengguna (kecuali pemegang attendance.view.all).
            'patterns' => SchedulePattern::query()->visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(),
            'defaultStart' => now()->startOfMonth()->toDateString(),
            'selectedEmployee' => $request->integer('employee_id') ?: null,
        ]);
    }

    public function store(ScheduleAssignmentRequest $request): RedirectResponse
    {
        $patternId = $request->integer('schedule_pattern_id');
        $start = Carbon::parse($request->date('start_date'));
        $end = $request->date('end_date') ? Carbon::parse($request->date('end_date')) : null;

        $days = 0;
        $scope = DataScope::forAttendance($request->user());

        // Hanya boleh menugaskan pola milik sendiri (kecuali pemegang attendance.view.all).
        abort_unless(
            SchedulePattern::query()->visibleTo($request->user())->whereKey($patternId)->exists(),
            403,
        );

        foreach ($request->input('employee_ids', []) as $employeeId) {
            $scope->authorize(Employee::find($employeeId));

            $assignment = ScheduleAssignment::query()->create([
                'employee_id' => $employeeId,
                'schedule_pattern_id' => $patternId,
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'created_by' => $request->user()->id,
            ]);

            $days += $this->generator->forAssignment($assignment);
        }

        return redirect()
            ->route('attendance.schedules.index', ['month' => $start->format('Y-m')])
            ->with('status', "Pola ditugaskan & {$days} hari jadwal dibuat.");
    }

    /**
     * (Re)generate the roster for a month across the current scope. Manual overrides
     * are preserved by the generator.
     */
    public function generate(Request $request): RedirectResponse
    {
        $month = $this->resolveMonth($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $branchId = $request->integer('branch_id') ?: null;

        // Regenerating only touches the roster of the employees this user may see.
        $employees = DataScope::forAttendance($request->user())
            ->employees()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        $days = 0;

        foreach ($employees as $employee) {
            $days += $this->generator->forEmployee($employee, $from, $to);
        }

        return redirect()
            ->route('attendance.schedules.index', $request->only('month', 'branch_id'))
            ->with('status', "Roster {$month->translatedFormat('F Y')} diperbarui ({$days} hari).");
    }

    public function override(ScheduleOverrideRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
        DataScope::forAttendance($request->user())->authorize($employee);

        $date = Carbon::parse($request->date('work_date'));

        $this->generator->override(
            $employee,
            $date,
            $request->boolean('is_day_off') ? null : $request->integer('shift_id'),
            $request->boolean('is_day_off'),
            $request->string('note')->toString() ?: null,
            $request->boolean('is_wfh'),
        );

        return redirect()
            ->route('attendance.schedules.index', ['month' => $date->format('Y-m'), 'branch_id' => $request->integer('branch_id') ?: null])
            ->with('status', 'Jadwal harian diperbarui.');
    }

    public function destroyAssignment(Request $request, ScheduleAssignment $assignment): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($assignment->employee);
        abort_unless(
            $request->user()->can(\App\Models\User::SCOPE_BYPASS_ATTENDANCE) || $assignment->created_by === $request->user()->id,
            403,
        );

        $month = Carbon::parse($assignment->start_date)->format('Y-m');
        $assignment->delete();

        return redirect()
            ->route('attendance.schedules.index', ['month' => $month])
            ->with('status', 'Penugasan pola dihapus. Jadwal yang sudah dibuat tetap tersimpan.');
    }

    private function resolveMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
