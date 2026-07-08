<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    public const STATUS_PENDING_PARTNER = 'pending_partner';
    public const STATUS_PENDING_HR = 'pending_hr';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_SWAP = 'swap';
    public const TYPE_COVER = 'cover';
    public const TYPE_DAYOFF = 'dayoff';

    /** @var list<string> */
    protected $fillable = [
        'requester_id',
        'requester_date',
        'partner_id',
        'partner_date',
        'type',
        'reason',
        'status',
        'partner_responded_at',
        'reviewed_by',
        'decided_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'requester_date' => 'date',
            'partner_date' => 'date',
            'partner_responded_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'partner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPendingPartner(): bool
    {
        return $this->status === self::STATUS_PENDING_PARTNER;
    }

    public function isPendingHr(): bool
    {
        return $this->status === self::STATUS_PENDING_HR;
    }

    public function scopePendingHr(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING_HR);
    }

    public function isCover(): bool
    {
        return $this->type === self::TYPE_COVER;
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_SWAP => 'Tukar Shift',
            self::TYPE_COVER => 'Ambil Alih (Cover)',
            self::TYPE_DAYOFF => 'Tukar Hari Libur',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_PARTNER => 'Menunggu Rekan',
            self::STATUS_PENDING_HR => 'Menunggu HR',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => $this->status,
        };
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_PARTNER, self::STATUS_PENDING_HR => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'neutral',
            default => 'neutral',
        };
    }
}
