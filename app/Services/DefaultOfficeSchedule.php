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
 * Resolves the "jam kantor" schedule for employees flagged follows_office_hours —
 * people whose weekly pattern never changes and who are therefore never scheduled
 * explicitly. Each day is derived from a pattern on the fly, so no roster rows are
 * materialized for these employees.
 *
 * Which pattern applies is resolved in two steps:
 *   1. the employee's own office_pattern_id, when set;
 *   2. otherwise the app-wide default chosen in Pengaturan (default_office_pattern_id).
 *
 * Step 2 alone is what the app did before per-employee patterns existed, and an
 * employee with no pattern of their own still lands there — so existing data keeps
 * behaving exactly as it did.
 *
 * Resolution deliberately loads a pattern by id without checking is_active or
 * is_office_pattern: those flags govern what may be OFFERED in the pickers, not what
 * an employee already follows. Retiring a pattern must never silently rewrite the
 * schedule — and therefore the attendance — of everyone still on it.
 */
class DefaultOfficeSchedule
{
    /** Setting key holding the id of the pattern used as the app-wide default. */
    public const SETTING_KEY = 'default_office_pattern_id';

    private bool $defaultLoaded = false;

    private ?int $defaultId = null;

    /**
     * Patterns already fetched this request, keyed by id. The roster grid calls
     * fill() once per employee, so without this a page of 200 people would issue 200
     * queries instead of one per distinct pattern in use.
     *
     * @var array<int, SchedulePattern|null>
     */
    private array $patterns = [];

    /**
     * The pattern this employee's office hours come from, or null when they are not
     * on office hours or no pattern applies to them.
     */
    public function patternFor(Employee $employee): ?SchedulePattern
    {
        if (! $employee->follows_office_hours) {
            return null;
        }

        $id = $employee->office_pattern_id ?: $this->defaultPatternId();

        return $id ? $this->pattern((int) $id) : null;
    }

    public function isConfiguredFor(Employee $employee): bool
    {
        return $this->patternFor($employee) !== null;
    }

    /**
     * A transient (unsaved) schedule row for the employee-day derived from the
     * pattern that applies to them, or null when none does. A day with no shift in
     * the pattern (e.g. Sunday) becomes a day off.
     */
    public function scheduleFor(Employee $employee, CarbonInterface $date): ?EmployeeSchedule
    {
        $pattern = $this->patternFor($employee);

        if (! $pattern) {
            return null;
        }

        $patternDay = $pattern->dayFor($date);
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
     * materialized row is never replaced). Returns $existing unchanged when no
     * office-hours pattern applies to the employee.
     *
     * @param  EloquentCollection<int, EmployeeSchedule>  $existing
     * @param  iterable<CarbonInterface>  $days
     * @return EloquentCollection<int, EmployeeSchedule>
     */
    public function fill(Employee $employee, EloquentCollection $existing, iterable $days): EloquentCollection
    {
        if (! $this->isConfiguredFor($employee)) {
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

        return new EloquentCollection($byDate->values()->all());
    }

    /**
     * The app-wide default pattern id, resolved once per instance.
     *
     * Publik karena halaman "karyawan pada pola ini" perlu tahu apakah pola yang
     * sedang dibuka kebetulan pola default — kalau ya, karyawan jam kantor yang tidak
     * menunjuk pola apa pun juga sebenarnya memakainya.
     */
    public function defaultPatternId(): ?int
    {
        if (! $this->defaultLoaded) {
            $this->defaultLoaded = true;
            $this->defaultId = ((int) Setting::get(self::SETTING_KEY)) ?: null;
        }

        return $this->defaultId;
    }

    /** Fetch a pattern (with its days + shifts) at most once per instance. */
    private function pattern(int $id): ?SchedulePattern
    {
        if (! array_key_exists($id, $this->patterns)) {
            $this->patterns[$id] = SchedulePattern::withTrashed()->with('days.shift')->find($id);
        }

        return $this->patterns[$id];
    }
}
