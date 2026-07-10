<?php

namespace App\Actions;

use App\Models\Employee;
use App\Services\AttendanceResolver;
use Carbon\CarbonInterface;

/**
 * Single source of truth for "resolve a whole day of attendance": used by both the
 * manual "Proses" button (AttendanceController) and the nightly scheduled command,
 * so both always produce identical results.
 *
 * For every active employee on the date it re-runs the resolver, which keeps any
 * punches already recorded and fills the gaps: scheduled-but-unpunched → Absent,
 * approved leave → its leave status, holidays → Holiday, unscheduled → DayOff.
 * Idempotent — safe to run repeatedly.
 */
class ProcessDayAttendance
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    public function handle(CarbonInterface $date, ?int $branchId = null): int
    {
        $employees = Employee::query()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        foreach ($employees as $employee) {
            $this->resolver->reprocess($employee, $date);
        }

        return $employees->count();
    }
}
