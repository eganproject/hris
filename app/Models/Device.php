<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'serial_number',
    'name',
    'branch_id',
    'timezone',
    'is_active',
    'last_seen_at',
    'last_ip',
    'options',
])]
class Device extends Model
{
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

    public function markSeen(?string $ip = null): void
    {
        $this->forceFill(['last_seen_at' => now(), 'last_ip' => $ip])->save();
    }
}
