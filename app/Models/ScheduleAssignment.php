<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ScheduleAssignment extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'schedule_pattern_id',
    'start_date',
    'end_date',
    'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(SchedulePattern::class, 'schedule_pattern_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Penugasan yang dibuat pengguna ini. Pemegang attendance.view.all melihat semua.
     */
    public function scopeVisibleToCreator(Builder $query, User $user): void
    {
        if ($user->can(User::SCOPE_BYPASS_ATTENDANCE)) {
            return;
        }

        $query->where('created_by', $user->id);
    }

    public function coversDate(CarbonInterface $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        if ($date->lessThan($this->start_date)) {
            return false;
        }

        return $this->end_date === null || $date->lessThanOrEqualTo($this->end_date);
    }

    /**
     * Assignments whose active period overlaps the given inclusive range.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereDate('start_date', '<=', $to)
            ->where(function (Builder $query) use ($from): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from);
            });
    }
}
