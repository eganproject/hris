<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    /** @var list<string> */
    protected $fillable = ['code', 'name', 'type', 'city', 'province', 'address', 'is_active',
        'mobile_attendance_enabled', 'latitude', 'longitude', 'geofence_radius_m', 'geofence_strict', 'wifi_bssids',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->withPivot(['is_primary', 'is_active'])
            ->withTimestamps();
    }

    public function activeDepartments(): BelongsToMany
    {
        return $this->departments()->wherePivot('is_active', true);
    }

    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->geofence_radius_m > 0;
    }

    /**
     * Jarak (meter) titik ke koordinat kantor, rumus haversine.
     */
    public function distanceTo(float $latitude, float $longitude): ?int
    {
        if (! $this->hasGeofence()) {
            return null;
        }

        $earth = 6371000;
        $dLat = deg2rad($latitude - $this->latitude);
        $dLng = deg2rad($longitude - $this->longitude);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($dLng / 2) ** 2;

        return (int) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Apakah BSSID Wi-Fi yang dilaporkan HP termasuk access point kantor ini.
     */
    public function matchesWifi(?string $bssid): bool
    {
        if (! $bssid || empty($this->wifi_bssids)) {
            return false;
        }

        $normalize = fn (string $value) => strtolower(str_replace('-', ':', trim($value)));

        return in_array($normalize($bssid), array_map($normalize, $this->wifi_bssids), true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mobile_attendance_enabled' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_m' => 'integer',
            'geofence_strict' => 'boolean',
            'wifi_bssids' => 'array',
        ];
    }
}
