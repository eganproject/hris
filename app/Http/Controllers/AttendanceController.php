<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\AttendancePunchRequest;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceResolver $resolver)
    {
    }

    /**
     * Daily attendance board: every active employee's resolved status for a date,
     * alongside their scheduled shift so gaps ("belum diproses") are visible.
     */
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->input('date'));
        $branchId = $request->integer('branch_id') ?: null;

        $employees = Employee::query()
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
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchId' => $branchId,
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
            'statuses' => AttendanceStatus::options(),
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

        $employees = Employee::query()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        foreach ($employees as $employee) {
            $this->resolver->reprocess($employee, $date);
        }

        return redirect()
            ->route('attendance.daily.index', $request->only('date', 'branch_id'))
            ->with('status', "Absensi {$date->translatedFormat('d M Y')} diproses ({$employees->count()} karyawan).");
    }

    /**
     * Manual punch entry/edit for one employee-day (stand-in for the fingerprint feed).
     */
    public function storePunch(AttendancePunchRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
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
}
