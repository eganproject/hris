<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OvertimeApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'supervisor_id',
        'work_date',
        'start_time',
        'end_time',
        'requested_minutes',
        'reason',
        'requested_at',
        'computed_minutes',
        'approved_minutes',
        'status',
        'reviewed_by',
        'decided_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_minutes' => 'integer',
            'computed_minutes' => 'integer',
            'approved_minutes' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Overtime minutes between start_time and end_time, treating an end that is not
     * after the start as crossing midnight (e.g. 22:00 → 01:00 = 180 minutes).
     */
    public static function minutesBetween(string $start, string $end): int
    {
        $startAt = Carbon::createFromFormat('H:i', $start);
        $endAt = Carbon::createFromFormat('H:i', $end);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt->addDay();
        }

        return (int) $startAt->diffInMinutes($endAt);
    }

    public function getTimeRangeLabelAttribute(): ?string
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }

    /** Store work_date as a pure Y-m-d string so the unique key & updateOrCreate match. */
    protected function workDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** The employee's direct manager who must approve the overtime. */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @return array<string, string> status value => label, for filter dropdowns.
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'neutral',
        };
    }
}
