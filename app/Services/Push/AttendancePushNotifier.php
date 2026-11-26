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

        $title = $type === 'in' ? 'Absen masuk tercatat' : 'Absen pulang tercatat';
        $where = $place ?: ($source === self::SOURCE_MACHINE ? 'mesin absensi' : 'tablet absensi');
        $day = $at->isToday() ? '' : ' ('.$at->translatedFormat('j M').')';
        $body = 'Pukul '.$at->format('H:i').$day.' di '.$where.'. Bukan Anda? Segera hubungi HR.';
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
