<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id',
    'employee_id',
    'machine_user_id',
    'punched_at',
    'state',
    'verify_mode',
    'status',
    'dedup_hash',
    'raw',
])]
class AttendancePunch extends Model
{
    public const STATUS_MATCHED = 'matched';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_IGNORED = 'ignored';

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'state' => 'integer',
            'verify_mode' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeUnmatched(Builder $query): void
    {
        $query->where('status', self::STATUS_UNMATCHED);
    }

    public function getVerifyLabelAttribute(): string
    {
        return match ($this->verify_mode) {
            1 => 'Sidik jari',
            15 => 'Wajah',
            0 => 'Password',
            default => 'Lainnya',
        };
    }
}
