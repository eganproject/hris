<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    /**
     * A device is considered "online" if it has contacted us within this window
     * (the X100-C polls roughly every 30s per its ADMS options).
     */
    public const ONLINE_WITHIN_MINUTES = 3;

    /** @var list<string> */
    protected $fillable = [
    'serial_number',
    'name',
    'branch_id',
    'timezone',
    'is_active',
    'last_seen_at',
    'last_ip',
    'options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'options' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(DeviceCommunication::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function latestCommunication(): HasOne
    {
        return $this->hasOne(DeviceCommunication::class)->latestOfMany();
    }

    public function markSeen(?string $ip = null): void
    {
        $this->forceFill(['last_seen_at' => now(), 'last_ip' => $ip])->save();
    }

    public function isOnline(): bool
    {
        return $this->is_active
            && $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subMinutes(self::ONLINE_WITHIN_MINUTES));
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) {
            return 'Nonaktif';
        }

        return $this->isOnline() ? 'Online' : 'Offline';
    }

    public function getStatusToneAttribute(): string
    {
        if (! $this->is_active) {
            return 'neutral';
        }

        return $this->isOnline() ? 'success' : 'danger';
    }
}
