<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris jejak aktivitas. Ditulis sekali, tidak pernah diubah.
 */
class ActivityLog extends Model
{
    /** Catatan audit tidak pernah disunting, jadi tidak punya updated_at. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'actor_name',
        'module',
        'event',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Modul, dipakai penyaring dan label kolom. */
    public const MODULES = [
        'auth' => 'Autentikasi',
        'employees' => 'Data Karyawan',
        'contracts' => 'Kontrak',
        'schedules' => 'Jadwal Kerja',
        'schedule-patterns' => 'Pola Jadwal',
        'shifts' => 'Shift Kerja',
        'leave' => 'Cuti & Izin',
        'leave-types' => 'Jenis Cuti',
        'attendance' => 'Absensi',
        'organization' => 'Organisasi',
        'devices' => 'Perangkat',
        'holidays' => 'Hari Libur',
        'users' => 'Pengguna & Akses',
        'settings' => 'Pengaturan',
    ];

    /** Jenis kejadian; nilainya juga menentukan warna lencana di tabel. */
    public const EVENTS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'restored' => 'Dipulihkan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'imported' => 'Impor',
        'exported' => 'Ekspor',
        'generated' => 'Digenerate',
        'login' => 'Masuk',
        'logout' => 'Keluar',
        'login_failed' => 'Gagal masuk',
        'login_blocked' => 'Diblokir sementara',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getModuleLabelAttribute(): string
    {
        return self::MODULES[$this->module] ?? str($this->module)->headline()->toString();
    }

    public function getEventLabelAttribute(): string
    {
        return self::EVENTS[$this->event] ?? str($this->event)->headline()->toString();
    }

    /** Warna lencana untuk event_label. */
    public function getEventToneAttribute(): string
    {
        return match ($this->event) {
            'created', 'approved', 'login', 'restored', 'imported' => 'success',
            'deleted', 'rejected', 'login_failed', 'login_blocked' => 'danger',
            'updated', 'generated' => 'info',
            default => 'neutral',
        };
    }

    /**
     * Nama pelaku yang selalu terbaca: akun yang masih ada dipakai lebih dulu,
     * jatuh ke salinan namanya, lalu ke "Sistem" untuk tugas terjadwal.
     */
    public function getActorLabelAttribute(): string
    {
        return $this->user?->name ?? $this->actor_name ?? 'Sistem';
    }

    /**
     * Ringkasan perubahan yang siap ditampilkan: "kolom: lama → baru".
     *
     * @return list<string>
     */
    public function getChangeSummaryAttribute(): array
    {
        $changes = $this->properties['changes'] ?? null;

        if (! is_array($changes)) {
            return [];
        }

        $render = function ($value): string {
            if ($value === null || $value === '') {
                return '(kosong)';
            }

            if (is_bool($value)) {
                return $value ? 'ya' : 'tidak';
            }

            return str(is_scalar($value) ? (string) $value : json_encode($value))->limit(60)->toString();
        };

        $lines = [];

        foreach ($changes as $field => $pair) {
            $lines[] = sprintf('%s: %s → %s', $field, $render($pair['dari'] ?? null), $render($pair['jadi'] ?? null));
        }

        return $lines;
    }

    /** Penyaring halaman aktivitas, dipakai bersama daftar dan ekspornya. */
    public function scopeFiltered(Builder $query, array $filters): void
    {
        $query
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['module'] ?? null, fn (Builder $q, $m) => $q->where('module', $m))
            ->when($filters['event'] ?? null, fn (Builder $q, $e) => $q->where('event', $e))
            ->when($filters['from'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($filters['search'] ?? null, fn (Builder $q, $s) => $q->where(fn (Builder $inner) => $inner
                ->where('description', 'like', "%{$s}%")
                ->orWhere('subject_label', 'like', "%{$s}%")
                ->orWhere('actor_name', 'like', "%{$s}%")
                ->orWhere('ip_address', 'like', "%{$s}%")));
    }
}
