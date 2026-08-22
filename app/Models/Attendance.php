<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    /** @var list<string> */
    protected $fillable = [
    'employee_id',
    'work_date',
    'shift_id',
    'status',
    'clock_in',
    'clock_out',
    'late_minutes',
    'early_leave_minutes',
    'work_minutes',
    'overtime_minutes',
    'leave_request_id',
    'holiday_id',
    'note',
    'clock_in_photo_path',
    'clock_in_latitude',
    'clock_in_longitude',
    'clock_in_accuracy_m',
    'clock_out_photo_path',
    'clock_out_latitude',
    'clock_out_longitude',
    'clock_out_accuracy_m',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'work_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'clock_in_latitude' => 'float',
            'clock_in_longitude' => 'float',
            'clock_in_accuracy_m' => 'integer',
            'clock_out_latitude' => 'float',
            'clock_out_longitude' => 'float',
            'clock_out_accuracy_m' => 'integer',
        ];
    }

    /**
     * Store work_date as a pure Y-m-d string so the (employee_id, work_date) unique
     * key and exact-match lookups stay reliable. Mirrors EmployeeSchedule.
     */
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    public function getClockInLabelAttribute(): string
    {
        return $this->clock_in?->format('H:i') ?? '–';
    }

    public function getClockOutLabelAttribute(): string
    {
        return $this->clock_out?->format('H:i') ?? '–';
    }

    /**
     * Disk privat: foto selfie hanya boleh keluar lewat rute berotorisasi.
     */
    public const SELFIE_DISK = 'local';

    /**
     * Bukti absen mandiri untuk satu sisi punch ('in' atau 'out'): URL foto, titik
     * koordinat dan tautan petanya. Null bila sisi itu tidak diabsen lewat selfie.
     *
     * URL fotonya menunjuk ke rute berotorisasi, bukan ke berkas di disk — berkasnya
     * memang tidak bisa dijangkau langsung dari web.
     *
     * @return array{photo_url: string, latitude: ?float, longitude: ?float, accuracy: ?int, map_url: ?string}|null
     */
    public function selfieFor(string $side): ?array
    {
        $path = $side === 'in' ? $this->clock_in_photo_path : $this->clock_out_photo_path;

        if (! $path) {
            return null;
        }

        $lat = $side === 'in' ? $this->clock_in_latitude : $this->clock_out_latitude;
        $lng = $side === 'in' ? $this->clock_in_longitude : $this->clock_out_longitude;

        return [
            'photo_url' => route('attendance.selfie', ['attendance' => $this->id, 'side' => $side]),
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $side === 'in' ? $this->clock_in_accuracy_m : $this->clock_out_accuracy_m,
            'map_url' => ($lat !== null && $lng !== null)
                ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}"
                : null,
        ];
    }

    public function hasSelfie(): bool
    {
        return $this->clock_in_photo_path !== null || $this->clock_out_photo_path !== null;
    }
}
