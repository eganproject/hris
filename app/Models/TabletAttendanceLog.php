<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu absen dari tablet wajah beserta foto bukti dan skornya.
 */
class TabletAttendanceLog extends Model
{
    public const RESULT_ACCEPTED = 'accepted';

    /** Pindai ulang dalam jeda singkat: tidak mengubah absensi. */
    public const RESULT_IGNORED = 'ignored';

    public const RESULT_REJECTED = 'rejected';

    /** Karyawan menekan "Bukan saya" di layar hasil. */
    public const RESULT_CANCELLED = 'cancelled';

    public const RESULT_LABELS = [
        self::RESULT_ACCEPTED => 'Diterima',
        self::RESULT_IGNORED => 'Diabaikan',
        self::RESULT_REJECTED => 'Ditolak',
        self::RESULT_CANCELLED => 'Dibatalkan ("Bukan saya")',
    ];

    /** Tanda untuk HR. Absen tetap tercatat; tanda hanya penanda. */
    public const FLAG_LABELS = [
        'pin_fallback' => 'Pakai NIK + PIN',
        'liveness_doubtful' => 'Cek wajah asli meragukan',
        'low_match' => 'Kecocokan wajah rendah',
        'offline_delay' => 'Terkirim terlambat (offline)',
        'clock_skew' => 'Jam tablet tidak sesuai',
        'other_branch' => 'Beda lokasi kerja',
        'repeat_out' => 'Pulang diperbarui',
    ];

    /** @var list<string> */
    protected $fillable = [
        'client_uuid', 'attendance_tablet_id', 'employee_id', 'attendance_id', 'branch_id', 'type', 'method',
        'result', 'reject_code', 'reject_reason', 'captured_at', 'received_at', 'work_date', 'match_score',
        'match_margin', 'liveness_checked', 'liveness_score', 'photo_path', 'flags',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
            'match_score' => 'float',
            'match_margin' => 'float',
            'liveness_checked' => 'boolean',
            'liveness_score' => 'float',
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

    public function tablet(): BelongsTo
    {
        return $this->belongsTo(AttendanceTablet::class, 'attendance_tablet_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function resultLabel(): string
    {
        return self::RESULT_LABELS[$this->result] ?? $this->result;
    }
}
