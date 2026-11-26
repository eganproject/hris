<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tablet Android di kantor untuk absen lewat pengenalan wajah. Didaftarkan HR di
 * web, terikat ke satu Lokasi Kerja, lalu dipasangkan dengan kode sekali pakai.
 * Tablet memegang token Sanctum sendiri (tokenable = tablet ini).
 */
class AttendanceTablet extends Model implements AuthenticatableContract
{
    // Authenticatable hanya agar guard Sanctum bisa memegang tablet sebagai
    // "pengguna" token; tablet tidak punya kata sandi dan tidak bisa login web.
    use Authenticatable, HasApiTokens;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    /** Tablet dianggap online bila menghubungi server dalam rentang ini. */
    public const ONLINE_MINUTES = 3;

    /** Kode pasangan berlaku selama ini sejak dibuat. */
    public const PAIRING_MINUTES = 60;

    /** @var list<string> */
    protected $fillable = ['name', 'branch_id', 'status', 'created_by'];

    /** @var list<string> */
    protected $hidden = ['pairing_code_hash', 'hr_pin_hash'];

    protected function casts(): array
    {
        return [
            'pairing_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'queue_count' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TabletAttendanceLog::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOnline(): bool
    {
        return $this->isActive() && $this->last_seen_at !== null && $this->last_seen_at->gte(now()->subMinutes(self::ONLINE_MINUTES));
    }

    /**
     * Buat kode pasangan baru (8 karakter, tanpa huruf yang mirip angka). Kode
     * hanya ditampilkan sekali di web; yang disimpan hash-nya.
     */
    public function issuePairingCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $this->forceFill([
            'pairing_code_hash' => Hash::make($code),
            'pairing_expires_at' => now()->addMinutes(self::PAIRING_MINUTES),
        ])->save();

        return $code;
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function pairingCodeMatches(string $code): bool
    {
        return $this->pairing_code_hash !== null
            && $this->pairing_expires_at?->isFuture()
            && Hash::check(self::normalizeCode($code), $this->pairing_code_hash);
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::STATUS_REVOKED => 'Dicabut',
            $this->status === self::STATUS_PENDING => 'Menunggu dipasangkan',
            $this->isOnline() => 'Online',
            $this->last_seen_at === null => 'Belum pernah aktif',
            default => 'Offline '.$this->last_seen_at->diffForHumans(null, true),
        };
    }
}
