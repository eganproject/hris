<?php

namespace App\Models;

use App\Enums\SchedulePatternType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SchedulePattern extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'code',
    'name',
    'type',
    'cycle_length',
    'anchor_date',
    'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => SchedulePatternType::class,
            'cycle_length' => 'integer',
            'anchor_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(SchedulePatternDay::class)->orderBy('day_index');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    /**
     * The number of distinct slots in this pattern's cycle. Weekly patterns always
     * have 7; rotating patterns use the configured cycle length.
     */
    public function slotCount(): int
    {
        return $this->type === SchedulePatternType::FixedWeekly
            ? 7
            : max(1, (int) $this->cycle_length);
    }

    /**
     * Which slot of the cycle a given calendar date falls on.
     * Weekly: Carbon dayOfWeek (0=Sunday..6=Saturday).
     * Rotating: days elapsed since the anchor date, wrapped into the cycle.
     */
    public function slotIndexFor(CarbonInterface $date): int
    {
        $date = Carbon::parse($date)->startOfDay();

        if ($this->type === SchedulePatternType::FixedWeekly) {
            return (int) $date->dayOfWeek;
        }

        $anchor = Carbon::parse($this->anchor_date ?? $date)->startOfDay();
        $cycle = $this->slotCount();
        $diff = (int) $anchor->diffInDays($date, false);

        return (($diff % $cycle) + $cycle) % $cycle;
    }

    /**
     * Resolve the pattern day for a date, or null when no slot is defined
     * (treated as a day off by the generator).
     */
    public function dayFor(CarbonInterface $date): ?SchedulePatternDay
    {
        return $this->days->firstWhere('day_index', $this->slotIndexFor($date));
    }
}
