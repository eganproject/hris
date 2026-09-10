<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'work_date',
        'requested_clock_in',
        'requested_clock_out',
        'reason',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'status',
        'reviewed_by',
        'decided_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'decided_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    /** Disk privat: buktinya hanya boleh keluar lewat rute berotorisasi. */
    public const ATTACHMENT_DISK = 'local';

    /**
     * Batas ukuran bukti. Disimpan di sini supaya aturan validasi, teks bantuan pada
     * formulir, dan penjaga di browser selalu menyebut angka yang sama.
     *
     * Lebih kecil daripada lampiran cuti (5 MB) karena yang diminta cuma satu
     * tangkapan layar atau foto layar CCTV, bukan hasil pindai dokumen.
     */
    public const ATTACHMENT_MAX_MB = 2;

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function attachmentSizeLabel(): string
    {
        $bytes = (int) $this->attachment_size;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => $this->status,
        };
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'neutral',
        };
    }
}
