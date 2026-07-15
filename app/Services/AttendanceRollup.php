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
    /** How early before the shift start a punch still belongs to the day (early arrival). */
    private const MARGIN_BEFORE_HOURS = 3;

    /** Normal late-departure buffer after the shift end. */
    private const MARGIN_AFTER_HOURS = 3;

    /**
     * How far past the shift end a clock-out can still be claimed by the day — big
     * enough to capture overtime that runs into the small hours on a normal day shift.
     * Always capped short of the next scheduled shift, so a punch is owned by one day.
     */
    private const MAX_OVERTIME_HOURS = 10;

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
        $date = Carbon::parse($date)->startOfDay();

        $shift = $this->shiftOn($employee, $date);

        if (! $shift) {
            return [$date->copy(), $date->copy()->addDay()];
        }

        $w = $shift->windowFor($date);
        $start = $w['start']->copy()->subHours(self::MARGIN_BEFORE_HOURS);

        // Overtime can push the clock-out well past the shift end — even across midnight
        // for a day shift. Extend the window to capture it, but never into the next
        // scheduled shift, so an overtime punch is owned by exactly one day.
        $end = $w['end']->copy()->addHours(self::MAX_OVERTIME_HOURS);

        $nextShift = $this->shiftOn($employee, $date->copy()->addDay());

        if ($nextShift) {
            $nextStart = $nextShift->windowFor($date->copy()->addDay())['start']
                ->copy()->subHours(self::MARGIN_BEFORE_HOURS);

            if ($end->greaterThan($nextStart)) {
                $end = $nextStart;
            }
        }

        // Never shrink below the normal late-departure buffer.
        $minEnd = $w['end']->copy()->addHours(self::MARGIN_AFTER_HOURS);

        return [$start, $end->lessThan($minEnd) ? $minEnd : $end];
    }

    private function shiftOn(Employee $employee, CarbonInterface $date): ?\App\Models\Shift
    {
        $schedule = $employee->schedules()
            ->whereDate('work_date', Carbon::parse($date)->toDateString())
            ->with('shift')
            ->first();

        return ($schedule && ! $schedule->is_day_off) ? $schedule->shift : null;
    }
}
