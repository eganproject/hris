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
    'is_wfh',
    ];

    protected function casts(): array
    {
        return [
            'day_index' => 'integer',
            'is_wfh' => 'boolean',
        ];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(SchedulePattern::class, 'schedule_pattern_id');
    }

    public function shift(): BelongsTo
    {
        // Lihat EmployeeSchedule::shift(): slot yang menunjuk shift terarsip harus
        // tetap terbaca generator, bukan berubah jadi hari libur.
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    public function isDayOff(): bool
    {
        return $this->shift_id === null;
    }
}
