<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleAssignmentRequest;
use App\Http\Requests\ScheduleOverrideRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Services\ScheduleGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $employees = Employee::query()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
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
            ->orderByDesc('start_date')
            ->get();

        return view('attendance.schedules.index', [
            'employees' => $employees,
            'days' => $days,
            'holidays' => $holidays,
            'assignments' => $assignments,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchId' => $branchId,
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
            'patternCount' => SchedulePattern::query()->where('is_active', true)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        // The picker shows each employee's still-running/upcoming assignments so the
        // user can see which periods are already taken before choosing new dates.
        $employees = Employee::query()
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
            'patterns' => SchedulePattern::query()->where('is_active', true)->orderBy('name')->get(),
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

        foreach ($request->input('employee_ids', []) as $employeeId) {
            $assignment = ScheduleAssignment::query()->create([
                'employee_id' => $employeeId,
                'schedule_pattern_id' => $patternId,
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
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

        $employees = Employee::query()
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
        $date = Carbon::parse($request->date('work_date'));

        $this->generator->override(
            $employee,
            $date,
            $request->boolean('is_day_off') ? null : $request->integer('shift_id'),
            $request->boolean('is_day_off'),
            $request->string('note')->toString() ?: null,
        );

        return redirect()
            ->route('attendance.schedules.index', ['month' => $date->format('Y-m'), 'branch_id' => $request->integer('branch_id') ?: null])
            ->with('status', 'Jadwal harian diperbarui.');
    }

    public function destroyAssignment(ScheduleAssignment $assignment): RedirectResponse
    {
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
