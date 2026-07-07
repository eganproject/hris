<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'code',
    'name',
    'start_time',
    'end_time',
    'crosses_midnight',
    'break_minutes',
    'late_tolerance_minutes',
    'early_leave_tolerance_minutes',
    'overtime_starts_after_minutes',
    'overtime_min_minutes',
    'is_active',
])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
            'break_minutes' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'early_leave_tolerance_minutes' => 'integer',
            'overtime_starts_after_minutes' => 'integer',
            'overtime_min_minutes' => 'integer',
        ];
    }

    /**
     * Resolve the concrete start/end datetimes of this shift for a given work date.
     * For overnight shifts the end lands on the next calendar day. This is the
     * foundation the attendance resolver will use to match punches to a shift.
     *
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    public function windowFor(CarbonInterface $workDate): array
    {
        $date = Carbon::parse($workDate)->startOfDay();

        $start = $date->copy()->setTimeFromTimeString((string) $this->start_time);
        $end = $date->copy()->setTimeFromTimeString((string) $this->end_time);

        if ($this->crosses_midnight || $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Gross shift length in minutes (handles overnight), before subtracting break.
     */
    public function getGrossMinutesAttribute(): ?int
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        $window = $this->windowFor(Carbon::today());

        return (int) $window['start']->diffInMinutes($window['end']);
    }

    /**
     * Net working minutes = gross minus break.
     */
    public function getWorkMinutesAttribute(): ?int
    {
        if ($this->gross_minutes === null) {
            return null;
        }

        return max(0, $this->gross_minutes - (int) $this->break_minutes);
    }

    /**
     * Overtime minutes earned for a given clock-out on a work date, applying the
     * shift's "starts after" grace and "minimum counted" threshold. This is what
     * the attendance resolver will call once real punches arrive.
     */
    public function overtimeMinutesFor(CarbonInterface $clockOut, CarbonInterface $workDate): int
    {
        $end = $this->windowFor($workDate)['end'];

        if ($clockOut->lessThanOrEqualTo($end)) {
            return 0;
        }

        $minutesPastEnd = (int) $end->diffInMinutes($clockOut);
        $counted = $minutesPastEnd - $this->overtime_starts_after_minutes;

        if ($counted <= 0) {
            return 0;
        }

        if ($this->overtime_min_minutes > 0 && $counted < $this->overtime_min_minutes) {
            return 0;
        }

        return $counted;
    }

    public function getOvertimeRuleLabelAttribute(): string
    {
        if ($this->overtime_starts_after_minutes === 0 && $this->overtime_min_minutes === 0) {
            return 'Sejak jam pulang';
        }

        return "Mulai +{$this->overtime_starts_after_minutes}m · min {$this->overtime_min_minutes}m";
    }

    public function getTimeRangeLabelAttribute(): string
    {
        $start = $this->start_time ? str($this->start_time)->substr(0, 5) : '--:--';
        $end = $this->end_time ? str($this->end_time)->substr(0, 5) : '--:--';

        return $start.' – '.$end.($this->crosses_midnight ? ' (+1)' : '');
    }
}
