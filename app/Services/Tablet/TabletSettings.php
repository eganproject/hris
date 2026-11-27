<?php

namespace App\Services\Tablet;

use App\Models\Setting;

/**
 * Pengaturan pengenalan wajah untuk semua tablet (menu Tablet Absensi Wajah).
 *
 * Angka ambang berasal dari uji model 2026-11-26 (MobileFaceNet w600k_mbf di LFW:
 * ambang 0,40 = 0 salah orang di simulasi 1:N) dan MiniFASNet untuk cek wajah asli.
 */
final class TabletSettings
{
    public const LIVENESS_KEY = 'face_tablet_liveness_enabled';

    public const MATCH_LEVEL_KEY = 'face_tablet_match_level';

    public const PIN_KEY = 'face_tablet_pin_enabled';

    public const IDLE_KEY = 'face_tablet_idle_minutes';

    /** Lama tanpa wajah sebelum layar istirahat muncul (0 = tidak pernah). */
    public const IDLE_OPTIONS = [
        0 => 'Tidak pernah',
        1 => '1 menit (disarankan)',
        2 => '2 menit',
        5 => '5 menit',
    ];

    /** Ambang kemiripan kosinus per tingkat kecocokan. */
    public const MATCH_LEVELS = [
        'loose' => ['label' => 'Longgar', 'threshold' => 0.35],
        'normal' => ['label' => 'Normal (disarankan)', 'threshold' => 0.40],
        'strict' => ['label' => 'Ketat', 'threshold' => 0.45],
    ];

    /** Selisih minimal skor kandidat pertama terhadap kandidat kedua. */
    public const MATCH_MARGIN = 0.06;

    /** Skor di bawah ambang + nilai ini diberi tanda "kecocokan rendah". */
    public const LOW_MATCH_BAND = 0.05;

    /** Skor cek wajah asli (0..1) minimal agar absen diterima di tablet. */
    public const LIVENESS_PASS = 0.60;

    /** Skor cek wajah asli di bawah ini (tapi lolos) diberi tanda untuk HR. */
    public const LIVENESS_DOUBTFUL = 0.85;

    /** Jumlah bingkai berturut-turut yang harus cocok ke orang yang sama. */
    public const CONSECUTIVE_FRAMES = 3;

    /** Pindai ulang orang yang sama dalam jeda ini diabaikan. */
    public const REPEAT_IGNORE_SECONDS = 60;

    public static function livenessEnabled(): bool
    {
        return Setting::getBool(self::LIVENESS_KEY, true);
    }

    public static function pinEnabled(): bool
    {
        return Setting::getBool(self::PIN_KEY, true);
    }

    public static function idleMinutes(): int
    {
        $minutes = (int) Setting::get(self::IDLE_KEY, 1);

        return array_key_exists($minutes, self::IDLE_OPTIONS) ? $minutes : 1;
    }

    public static function matchLevel(): string
    {
        $level = (string) Setting::get(self::MATCH_LEVEL_KEY, 'normal');

        return array_key_exists($level, self::MATCH_LEVELS) ? $level : 'normal';
    }

    public static function matchThreshold(): float
    {
        return self::MATCH_LEVELS[self::matchLevel()]['threshold'];
    }

    /** @return array<string, mixed> dikirim ke tablet saat sinkron. */
    public static function forTablet(): array
    {
        return [
            'liveness_enabled' => self::livenessEnabled(),
            'liveness_pass' => self::LIVENESS_PASS,
            'liveness_doubtful' => self::LIVENESS_DOUBTFUL,
            'match_level' => self::matchLevel(),
            'match_threshold' => self::matchThreshold(),
            'match_margin' => self::MATCH_MARGIN,
            'consecutive_frames' => self::CONSECUTIVE_FRAMES,
            'repeat_ignore_seconds' => self::REPEAT_IGNORE_SECONDS,
            'pin_enabled' => self::pinEnabled(),
            'idle_minutes' => self::idleMinutes(),
        ];
    }
}
