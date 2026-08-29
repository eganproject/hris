<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Mencatat kejadian autentikasi.
 *
 * Percobaan yang GAGAL justru yang paling perlu tercatat: itulah satu-satunya jejak
 * yang tersisa dari upaya masuk tanpa hak. Pada kejadian itu pelakunya tidak diketahui
 * — jadi barisnya ditulis atas nama "(tidak dikenal)", bukan atas nama pemilik akun
 * yang justru sedang jadi sasaran.
 */
class RecordAuthActivity
{
    private const UNKNOWN = '(tidak dikenal)';

    public function handleLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        ActivityLogger::auth('login', 'Masuk ke aplikasi.', $user, [], $user);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        if (! $user) {
            return;
        }

        ActivityLogger::auth('logout', 'Keluar dari aplikasi.', $user, [], $user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? '(tanpa email)';
        $account = $event->user instanceof User ? $event->user : null;

        ActivityLogger::auth(
            'login_failed',
            $account
                ? "Gagal masuk sebagai {$email} — kata sandi salah."
                : "Gagal masuk sebagai {$email} — akun tidak terdaftar.",
            $account,
            ['email' => $email, 'akun_terdaftar' => $account !== null],
            null,
            self::UNKNOWN,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email');

        ActivityLogger::auth(
            'login_blocked',
            "Percobaan masuk untuk {$email} diblokir sementara karena terlalu sering gagal.",
            null,
            ['email' => $email],
            null,
            self::UNKNOWN,
        );
    }
}
