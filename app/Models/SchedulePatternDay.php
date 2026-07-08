<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulePatternDay extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'schedule_pattern_id',
    'day_index',
    'shift_id',
    ];

    protected function casts(): array
    {
        return [
            'day_index' => 'integer',
        ];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(SchedulePattern::class, 'schedule_pattern_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function isDayOff(): bool
    {
        return $this->shift_id === null;
    }
}
