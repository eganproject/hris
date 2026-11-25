<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HP yang terikat ke seorang karyawan untuk absen lewat aplikasi.
 * Satu karyawan = satu HP aktif; pergantian HP disetujui HR.
 */
class MobileDevice extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Aktif',
        self::STATUS_PENDING => 'Menunggu persetujuan',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_REVOKED => 'Dicabut',
    ];

    /** Awalan nama token Sanctum: "mobile:<id>". */
    public const TOKEN_PREFIX = 'mobile:';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'user_id',
        'device_uuid',
        'platform',
        'model',
        'os_version',
        'app_version',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
        'consented_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function tokenName(): string
    {
        return self::TOKEN_PREFIX.$this->id;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getLabelAttribute(): string
    {
        return trim(($this->model ?: 'HP').' · '.ucfirst($this->platform).' '.($this->os_version ?? ''));
    }
}
