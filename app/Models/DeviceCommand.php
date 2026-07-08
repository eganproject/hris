<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'label',
        'command',
        'status',
        'return_code',
        'created_by',
        'sent_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'return_code' => 'integer',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_DONE => 'Selesai',
            self::STATUS_FAILED => 'Gagal',
            default => $this->status,
        };
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_SENT => 'info',
            self::STATUS_DONE => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'neutral',
        };
    }
}
