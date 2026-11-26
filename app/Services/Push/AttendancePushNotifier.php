<?php

namespace App\Services\Push;

use App\Models\Employee;
use App\Models\Setting;
use App\Notifications\ApprovalNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * "Absen masuk tercatat pukul 08:02" ke HP karyawan setiap absennya masuk dari
 * tablet kantor atau mesin absensi. Dicatat juga di daftar notifikasi aplikasi.
 *
 * Pengirimannya ditunda sampai respons ke tablet/mesin selesai, supaya
 * lambatnya Firebase tidak memperlambat absen.
 */
class AttendancePushNotifier
{
    public const IN_KEY = 'push_attendance_in';

    public const OUT_KEY = 'push_attendance_out';

    public const MACHINE_KEY = 'push_attendance_machine';

    /** Kata-kata notifikasi yang bisa diubah HR di menu Pengaturan. */
    public const TEMPLATE_KEYS = [
        'in' => ['title' => 'push_attendance_in_title', 'body' => 'push_attendance_in_body'],
        'out' => ['title' => 'push_attendance_out_title', 'body' => 'push_attendance_out_body'],
    ];

    public const DEFAULT_TEMPLATES = [
        'in' => ['title' => 'Absen masuk tercatat', 'body' => 'Pukul {jam} di {lokasi}. Bukan Anda? Segera hubungi HR.'],
        'out' => ['title' => 'Absen pulang tercatat', 'body' => 'Pukul {jam} di {lokasi}. Terima kasih, hati-hati di jalan.'],
    ];

    /** Penanda yang bisa dipakai di kata-kata notifikasi. */
    public const PLACEHOLDERS = [
        '{nama}' => 'nama panggilan (kata pertama nama karyawan)',
        '{nama_lengkap}' => 'nama lengkap karyawan',
        '{jam}' => 'jam absen, mis. 08:02',
        '{tanggal}' => 'tanggal absen, mis. 26 Nov 2026',
        '{lokasi}' => 'nama tablet atau mesin absensi',
    ];

    public const SOURCE_TABLET = 'tablet';

    public const SOURCE_MACHINE = 'machine';

    public function __construct(private readonly PushNotifier $push) {}

    public static function enabledFor(string $type, string $source): bool
    {
        if ($source === self::SOURCE_MACHINE && ! Setting::getBool(self::MACHINE_KEY, true)) {
            return false;
        }

        return $type === 'in'
            ? Setting::getBool(self::IN_KEY, true)
            : Setting::getBool(self::OUT_KEY, true);
    }

    /**
     * @param  'in'|'out'  $type
     * @return array{title: string, body: string}
     */
    public static function template(string $type): array
    {
        $keys = self::TEMPLATE_KEYS[$type];
        $default = self::DEFAULT_TEMPLATES[$type];

        return [
            'title' => trim((string) Setting::get($keys['title'])) ?: $default['title'],
            'body' => trim((string) Setting::get($keys['body'])) ?: $default['body'],
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function fill(string $text, array $values): string
    {
        return trim(preg_replace('/\s+/', ' ', strtr($text, $values)));
    }

    /**
     * @param  'in'|'out'  $type
     */
    public function clocked(Employee $employee, string $type, CarbonInterface $at, string $source, ?string $place = null): void
    {
        if (! self::enabledFor($type, $source)) {
            return;
        }

        $user = $employee->user;

        if (! $user) {
            return;
        }

        $name = trim((string) $employee->full_name);
        $values = [
            '{nama}' => strtok($name, ' ') ?: $name,
            '{nama_lengkap}' => $name,
            '{jam}' => $at->format('H:i'),
            '{tanggal}' => $at->translatedFormat('j M Y'),
            '{lokasi}' => $place ?: ($source === self::SOURCE_MACHINE ? 'mesin absensi' : 'tablet absensi'),
        ];
        $template = self::template($type);
        $title = self::fill($template['title'], $values);
        $body = self::fill($template['body'], $values);
        $meta = [
            'type' => $type,
            'source' => $source,
            'work_date' => $at->toDateString(),
            'time' => $at->format('H:i'),
        ];

        $user->notify(new ApprovalNotification(
            title: $title,
            message: $body,
            url: route('my-attendance.index'),
            category: 'attendance',
            meta: $meta,
        ));

        defer(function () use ($user, $title, $body, $meta) {
            try {
                $this->push->toUser($user, $title, $body, ['category' => 'attendance', ...$meta]);
            } catch (Throwable $e) {
                Log::warning('Push absen gagal.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
