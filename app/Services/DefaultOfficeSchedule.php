<?php

namespace App\Services;

use App\Enums\ScheduleSource;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\SchedulePattern;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

/**
 * Resolves the "jam kantor default" schedule for employees flagged
 * follows_office_hours — people whose weekly pattern never changes and who are
 * therefore never scheduled explicitly. The reference pattern is chosen once in
 * Pengaturan (default_office_pattern_id); each day is derived from it on the fly,
 * so no roster rows are materialized for these employees.
 */
class DefaultOfficeSchedule
{
    /** Setting key holding the id of the pattern used as the office-hours default. */
    public const SETTING_KEY = 'default_office_pattern_id';

    private bool $loaded = false;

    private ?SchedulePattern $pattern = null;

    /**
     * The configured default office pattern (with its days + shifts eager-loaded),
     * or null when none is set. Resolved once per instance.
     */
    public function pattern(): ?SchedulePattern
    {
        if (! $this->loaded) {
            $this->loaded = true;

            $id = Setting::get(self::SETTING_KEY);

            $this->pattern = $id
                ? SchedulePattern::query()->with('days.shift')->find($id)
                : null;
        }

        return $this->pattern;
    }

    public function isConfigured(): bool
    {
        return $this->pattern() !== null;
    }

    /**
     * A transient (unsaved) schedule row for the employee-day derived from the
     * default office pattern, or null when the employee does not follow office
     * hours or no default pattern is configured. A day with no shift in the pattern
     * (e.g. Sunday) becomes a day off.
     */
    public function scheduleFor(Employee $employee, CarbonInterface $date): ?EmployeeSchedule
    {
        if (! $employee->follows_office_hours || ! $this->isConfigured()) {
            return null;
        }

        $patternDay = $this->pattern()->dayFor($date);
        $shift = $patternDay?->shift;

        $schedule = new EmployeeSchedule([
            'employee_id' => $employee->id,
            'work_date' => Carbon::parse($date)->toDateString(),
            'shift_id' => $shift?->id,
            'is_day_off' => $shift === null,
            // WFH hanya berlaku pada hari kerja (ada shift-nya).
            'is_wfh' => (bool) ($patternDay?->is_wfh && $shift !== null),
            'source' => ScheduleSource::Generated,
        ]);

        // Sediakan relasi shift agar pembaca (grid/resolver) tak query ulang.
        $schedule->setRelation('shift', $shift);

        return $schedule;
    }

    /**
     * Merge synthesized office-hour rows into an existing keyed schedule collection
     * for the given days. Real rows always win (a manual override or leftover
     * materialized row is never replaced). Returns $existing unchanged when the
     * employee does not follow office hours or no default pattern is set.
     *
     * @param  EloquentCollection<int, EmployeeSchedule>  $existing
     * @param  iterable<CarbonInterface>  $days
     * @return EloquentCollection<int, EmployeeSchedule>
     */
    public function fill(Employee $employee, EloquentCollection $existing, iterable $days): EloquentCollection
    {
        if (! $employee->follows_office_hours || ! $this->isConfigured()) {
            return $existing;
        }

        $byDate = $existing->keyBy(fn (EmployeeSchedule $schedule) => $schedule->work_date->toDateString());

        foreach ($days as $day) {
            $key = Carbon::parse($day)->toDateString();

            if ($byDate->has($key)) {
                continue; // real row wins
            }

            if ($synth = $this->scheduleFor($employee, $day)) {
                $byDate->put($key, $synth);
            }
        }

        return $existing->newCollection($byDate->values()->all());
    }
}
