<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommunication extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'event',
        'records_count',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'records_count' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            'handshake' => 'Handshake',
            'attlog' => 'Kirim absensi',
            'poll' => 'Polling',
            'command' => 'Perintah',
            default => 'Data',
        };
    }

    public function getEventToneAttribute(): string
    {
        return match ($this->event) {
            'attlog' => 'success',
            'handshake' => 'info',
            'command' => 'warning',
            default => 'neutral',
        };
    }
}
