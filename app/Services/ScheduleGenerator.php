<?php

namespace App\Services;

use App\Enums\ScheduleSource;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ScheduleGenerator
{
    /**
     * Default horizon (in days) to materialize for an open-ended assignment so the
     * roster is populated without an explicit end date.
     */
    public const DEFAULT_HORIZON_DAYS = 90;

    /**
     * Materialize the daily schedule for one employee across an inclusive date range.
     * For each day the applicable assignment (latest-starting one covering that day)
     * decides the shift via its pattern. Existing manual overrides are preserved.
     *
     * @return int number of days written or refreshed
     */
    public function forEmployee(Employee $employee, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($to->lessThan($from)) {
            return 0;
        }

        $assignments = $employee->scheduleAssignments()
            ->overlapping($from, $to)
            ->with('pattern.days')
            ->orderBy('start_date')
            ->get();

        // Pull existing rows once so we can respect manual overrides without a query per day.
        $existing = $employee->schedules()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (EmployeeSchedule $row) => $row->work_date->toDateString());

        $written = 0;

        for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
            $key = $date->toDateString();

            if (($existing[$key] ?? null)?->isManual()) {
                continue; // never clobber a manual override
            }

            $assignment = $this->assignmentFor($assignments, $date);

            if (! $assignment) {
                continue; // no pattern governs this day; leave any existing row untouched
            }

            $patternDay = $assignment->pattern?->dayFor($date);

            EmployeeSchedule::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $key],
                [
                    'shift_id' => $patternDay?->shift_id,
                    'is_day_off' => $patternDay === null || $patternDay->shift_id === null,
                    // WFH hanya berlaku pada hari kerja (ada shift-nya).
                    'is_wfh' => (bool) ($patternDay?->is_wfh && $patternDay->shift_id !== null),
                    'source' => ScheduleSource::Generated,
                    'schedule_assignment_id' => $assignment->id,
                    'note' => null,
                ],
            );

            $written++;
        }

        return $written;
    }

    /**
     * Materialize the schedule implied by a single assignment. Open-ended assignments
     * are bounded by DEFAULT_HORIZON_DAYS (or an explicit $to).
     */
    public function forAssignment(ScheduleAssignment $assignment, ?CarbonInterface $to = null): int
    {
        $from = Carbon::parse($assignment->start_date)->startOfDay();

        $end = $assignment->end_date
            ? Carbon::parse($assignment->end_date)->startOfDay()
            : ($to ? Carbon::parse($to)->startOfDay() : $from->copy()->addDays(self::DEFAULT_HORIZON_DAYS));

        return $this->forEmployee($assignment->employee, $from, $end);
    }

    /**
     * Set or clear a single day as a manual override that the generator won't touch.
     */
    public function override(Employee $employee, CarbonInterface $date, ?int $shiftId, bool $isDayOff, ?string $note = null, bool $isWfh = false): EmployeeSchedule
    {
        return EmployeeSchedule::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => Carbon::parse($date)->toDateString()],
            [
                'shift_id' => $isDayOff ? null : $shiftId,
                'is_day_off' => $isDayOff,
                // WFH hanya berlaku pada hari kerja (bukan libur, dan ada shift-nya).
                'is_wfh' => $isWfh && ! $isDayOff && $shiftId !== null,
                'source' => ScheduleSource::Manual,
                'note' => $note,
            ],
        );
    }

    /**
     * Pick the assignment that governs a date: the latest-starting one that covers it.
     *
     * @param  \Illuminate\Support\Collection<int, ScheduleAssignment>  $assignments
     */
    private function assignmentFor($assignments, CarbonInterface $date): ?ScheduleAssignment
    {
        return $assignments
            ->filter(fn (ScheduleAssignment $assignment) => $assignment->coversDate($date))
            ->sortByDesc('start_date')
            ->first();
    }
}
