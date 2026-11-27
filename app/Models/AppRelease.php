<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu berkas APK yang diunggah ke server HRIS.
 *
 * - employee: aplikasi HP karyawan, versi terbarunya bisa diunduh siapa saja
 *   lewat halaman publik /unduh-aplikasi (tetap harus login di aplikasinya).
 * - tablet: aplikasi tablet absensi wajah, hanya superadmin yang bisa mengunduh.
 */
class AppRelease extends Model
{
    public const DISK = 'local';

    public const EMPLOYEE = 'employee';

    public const TABLET = 'tablet';

    /** Label + nama paket Android yang wajib cocok dengan APK yang diunggah. */
    public const APPS = [
        self::EMPLOYEE => [
            'label' => 'Aplikasi HP karyawan',
            'package' => 'com.cahayaoptimakarya.hris_mobile',
            'filename' => 'hris-karyawan',
        ],
        self::TABLET => [
            'label' => 'Aplikasi tablet absensi wajah',
            'package' => 'com.cahayaoptimakarya.hris_tablet',
            'filename' => 'hris-tablet-absensi',
        ],
    ];

    protected $fillable = [
        'app', 'package_name', 'version_code', 'version_name', 'file_path',
        'file_size', 'sha256', 'notes', 'is_mandatory', 'uploaded_by', 'source',
    ];

    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'file_size' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Versi terbaru (versionCode tertinggi, lalu unggahan terakhir). */
    public static function latestFor(string $app): ?self
    {
        return static::query()
            ->where('app', $app)
            ->orderByDesc('version_code')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * versionCode minimum yang masih boleh dipakai: versi wajib tertinggi.
     * 0 berarti belum ada versi wajib (semua versi boleh dipakai).
     */
    public static function minimumVersionCode(string $app): int
    {
        return (int) static::query()->where('app', $app)->where('is_mandatory', true)->max('version_code');
    }

    /**
     * Jawaban cek update untuk aplikasi yang sedang terpasang di versi $current.
     *
     * @return array<string, mixed>
     */
    public static function updateInfo(string $app, int $current, string $downloadUrl): array
    {
        $latest = static::latestFor($app);
        $minimum = static::minimumVersionCode($app);
        $available = $latest !== null && $latest->version_code > $current;

        return [
            'current_version_code' => $current,
            'min_version_code' => $minimum,
            'update_available' => $available,
            // Hanya wajib bila memang ada APK lebih baru untuk dipasang.
            'update_required' => $available && $current < $minimum,
            'latest' => $latest ? [
                'version_code' => $latest->version_code,
                'version_name' => $latest->version_name,
                'size' => $latest->file_size,
                'sha256' => $latest->sha256,
                'notes' => $latest->notes,
                'released_at' => $latest->created_at?->toIso8601String(),
                'download_url' => $downloadUrl,
            ] : null,
        ];
    }

    public function label(): string
    {
        return self::APPS[$this->app]['label'] ?? $this->app;
    }

    public function downloadName(): string
    {
        $base = self::APPS[$this->app]['filename'] ?? 'aplikasi';

        return "{$base}-{$this->version_name}.apk";
    }

    public function sizeLabel(): string
    {
        return number_format($this->file_size / 1024 / 1024, 1, ',', '.').' MB';
    }
}
