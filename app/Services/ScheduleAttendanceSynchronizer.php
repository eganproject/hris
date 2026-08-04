<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ScheduleAttendanceSynchronizer
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    /**
     * Re-resolve attendance rows that already exist after their roster changes.
     * Missing rows are intentionally left for the normal daily attendance process.
     */
    public function forRange(Employee $employee, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($to->lessThan($from)) {
            return 0;
        }

        $dates = $employee->attendances()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('work_date');

        foreach ($dates as $date) {
            $this->resolver->reprocess($employee, Carbon::parse($date));
        }

        return $dates->count();
    }

    public function forDate(Employee $employee, CarbonInterface $date): int
    {
        return $this->forRange($employee, $date, $date);
    }
}
