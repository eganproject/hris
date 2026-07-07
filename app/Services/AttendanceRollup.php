<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turns raw device punches into the clock-in/out for an employee-day and hands
 * them to the AttendanceResolver. This is the bridge between the fingerprint feed
 * and the resolved attendance.
 */
class AttendanceRollup
{
    /**
     * How far before the shift start / after the shift end we still consider a punch
     * to belong to that day (early arrival, late departure, clock buffer).
     */
    private const MARGIN_HOURS = 3;

    public function __construct(private readonly AttendanceResolver $resolver)
    {
    }

    /**
     * Rebuild the attendance for one employee-day from device punches. Returns null
     * (and leaves any existing row untouched) when there are no punches in the window,
     * so this never erases manually-entered attendance.
     */
    public function rebuild(Employee $employee, CarbonInterface $date): ?Attendance
    {
        $date = Carbon::parse($date)->startOfDay();

        [$from, $to] = $this->window($employee, $date);

        $punches = $employee->punches()
            ->where('status', 'matched')
            ->whereBetween('punched_at', [$from, $to])
            ->orderBy('punched_at')
            ->get();

        if ($punches->isEmpty()) {
            return null;
        }

        $first = $punches->first()->punched_at;
        $last = $punches->last()->punched_at;

        $clockIn = $first->format('H:i');
        $clockOut = $last->equalTo($first) ? null : $last->format('H:i');

        return $this->resolver->resolve($employee, $date, $clockIn, $clockOut);
    }

    /**
     * The datetime window that "owns" punches for a work date. For a scheduled shift
     * it is the shift window (handles overnight) plus a margin; otherwise the calendar day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Employee $employee, CarbonInterface $date): array
    {
        $schedule = $employee->schedules()
            ->whereDate('work_date', Carbon::parse($date)->toDateString())
            ->with('shift')
            ->first();

        $shift = ($schedule && ! $schedule->is_day_off) ? $schedule->shift : null;

        if ($shift) {
            $w = $shift->windowFor($date);

            return [
                $w['start']->copy()->subHours(self::MARGIN_HOURS),
                $w['end']->copy()->addHours(self::MARGIN_HOURS),
            ];
        }

        return [
            Carbon::parse($date)->startOfDay(),
            Carbon::parse($date)->addDay()->startOfDay(),
        ];
    }
}
