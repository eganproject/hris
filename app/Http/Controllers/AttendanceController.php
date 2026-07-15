<?php

namespace App\Http\Controllers;

use App\Actions\ProcessDayAttendance;
use App\Enums\AttendanceStatus;
use App\Http\Requests\AttendancePunchRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceResolver;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceResolver $resolver,
        private readonly ProcessDayAttendance $processDay,
    ) {}

    /**
     * Daily attendance board: every active employee's resolved status for a date,
     * alongside their scheduled shift so gaps ("belum diproses") are visible.
     */
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->input('date'));
        $branchId = $request->integer('branch_id') ?: null;
        $scope = DataScope::forAttendance($request->user());

        $employees = $scope->employees()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->with([
                'attendances' => fn ($query) => $query->whereDate('work_date', $date->toDateString())->with('shift'),
                'schedules' => fn ($query) => $query->whereDate('work_date', $date->toDateString())->with('shift'),
            ])
            ->orderBy('full_name')
            ->get();

        // Status tally for the summary strip.
        $summary = $employees
            ->map(fn (Employee $e) => $e->attendances->first()?->status)
            ->filter()
            ->countBy(fn (AttendanceStatus $status) => $status->value);

        return view('attendance.daily.index', [
            'employees' => $employees,
            'summary' => $summary,
            'date' => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'branches' => $scope->branches(),
            'branchId' => $branchId,
            'hasNoScope' => $scope->isEmpty(),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
            'statuses' => AttendanceStatus::options(),
        ]);
    }

    /**
     * Per-employee attendance history for one month, reachable by clicking an
     * employee on the daily board. Same scope gate as the board itself.
     */
    public function history(Request $request, Employee $employee): View
    {
        DataScope::forAttendance($request->user())->authorize($employee);

        $month = $this->resolveMonth($request->input('month'));
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->with('shift')
            ->orderBy('work_date')
            ->get();

        // Approved overtime per date (authoritative figure), keyed by Y-m-d.
        $approvedOvertime = \Illuminate\Support\Facades\DB::table('overtime_approvals')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from, $to])
            ->pluck('approved_minutes', 'work_date');

        $worked = ['present', 'late', 'early_leave', 'wfh', 'business_trip'];

        return view('attendance.daily.history', [
            'employee' => $employee->load(['branch', 'departments', 'jobPosition']),
            'records' => $records,
            'approvedOvertime' => $approvedOvertime,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branchId' => $request->integer('branch_id') ?: null,
            'summary' => [
                'total_hari' => $records->count(),
                'hadir' => $records->filter(fn ($r) => in_array($r->status?->value, $worked, true))->count(),
                'terlambat' => $records->filter(fn ($r) => $r->status?->value === 'late')->count(),
                'alfa' => $records->filter(fn ($r) => $r->status?->value === 'absent')->count(),
                'terlambat_menit' => (int) $records->sum('late_minutes'),
                'kerja_menit' => (int) $records->sum('work_minutes'),
                'lembur_menit' => (int) $approvedOvertime->sum(),
            ],
        ]);
    }

    /**
     * Resolve/refresh the whole date for the current scope. Existing punches are
     * preserved; unscheduled days become DayOff, scheduled-but-unpunched become Absent.
     */
    public function process(Request $request): RedirectResponse
    {
        $date = $this->resolveDate($request->input('date'));
        $branchId = $request->integer('branch_id') ?: null;

        // Only the employees this user may see get (re)processed.
        $count = $this->processDay->handle($date, $branchId, $request->user());

        return redirect()
            ->route('attendance.daily.index', $request->only('date', 'branch_id'))
            ->with('status', "Absensi {$date->translatedFormat('d M Y')} diproses ({$count} karyawan).");
    }

    /**
     * Manual punch entry/edit for one employee-day (stand-in for the fingerprint feed).
     */
    public function storePunch(AttendancePunchRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
        DataScope::forAttendance($request->user())->authorize($employee);

        $date = Carbon::parse($request->date('work_date'));

        $this->resolver->resolve(
            $employee,
            $date,
            $request->string('clock_in')->toString() ?: null,
            $request->string('clock_out')->toString() ?: null,
            $request->string('note')->toString() ?: null,
        );

        return redirect()
            ->route('attendance.daily.index', ['date' => $date->toDateString(), 'branch_id' => $request->integer('branch_id') ?: null])
            ->with('status', 'Absensi karyawan diperbarui.');
    }

    private function resolveDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
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
