<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu absen dari aplikasi HP (diterima atau ditolak) beserta buktinya.
 */
class MobileAttendanceLog extends Model
{
    public const RESULT_ACCEPTED = 'accepted';

    public const RESULT_REJECTED = 'rejected';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const MODE_LABELS = [
        'office' => 'Kantor',
        'wfh' => 'WFH',
        'business_trip' => 'Dinas luar',
    ];

    /** Tanda yang membuat absen masuk antrean tinjauan HR. */
    public const FLAG_LABELS = [
        'outside_radius' => 'Di luar radius kantor',
        'no_location' => 'Lokasi tidak terbaca',
        'low_accuracy' => 'Akurasi GPS rendah',
        // Tidak lagi dipasang; label disimpan untuk log lama.
        'blink_unverified' => 'Kedip tidak terverifikasi',
        'offline_delay' => 'Terkirim terlambat (offline)',
        'clock_skew' => 'Jam HP tidak sesuai',
        'no_geofence' => 'Titik kantor belum diatur',
    ];

    /** @var list<string> */
    protected $fillable = [
        'client_uuid', 'employee_id', 'mobile_device_id', 'attendance_id', 'type', 'mode', 'result',
        'reject_code', 'reject_reason', 'captured_at', 'received_at', 'work_date', 'branch_id',
        'latitude', 'longitude', 'accuracy_m', 'distance_m', 'within_radius', 'wifi_ssid', 'wifi_bssid',
        'wifi_matched', 'photo_path', 'blink_verified', 'is_mock_location', 'is_rooted', 'flags', 'employee_note',
        'review_status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'integer',
            'distance_m' => 'integer',
            'within_radius' => 'boolean',
            'wifi_matched' => 'boolean',
            'blink_verified' => 'boolean',
            'is_mock_location' => 'boolean',
            'is_rooted' => 'boolean',
            'flags' => 'array',
        ];
    }

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

    public function device(): BelongsTo
    {
        return $this->belongsTo(MobileDevice::class, 'mobile_device_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isAccepted(): bool
    {
        return $this->result === self::RESULT_ACCEPTED;
    }

    /** @return list<string> */
    public function flagLabels(): array
    {
        return array_map(fn (string $flag) => self::FLAG_LABELS[$flag] ?? $flag, $this->flags ?? []);
    }

    public function getModeLabelAttribute(): string
    {
        return self::MODE_LABELS[$this->mode] ?? $this->mode;
    }

    public function mapUrl(): ?string
    {
        return ($this->latitude !== null && $this->longitude !== null)
            ? "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}"
            : null;
    }
}
