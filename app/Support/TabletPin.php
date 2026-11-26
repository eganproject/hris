<?php

namespace App\Support;

/**
 * Hash PIN untuk tablet absensi (PIN HR tablet dan PIN cadangan karyawan).
 *
 * Tablet harus bisa memeriksa PIN saat internet putus, jadi hash ikut diunduh ke
 * tablet. Formatnya PBKDF2-SHA256 yang juga dihitung ulang di aplikasi Flutter:
 *   pbkdf2_sha256$<iterasi>$<salt base64>$<hash base64>
 */
final class TabletPin
{
    public const ITERATIONS = 20000;

    public static function hash(string $pin): string
    {
        $salt = random_bytes(16);
        $hash = hash_pbkdf2('sha256', $pin, $salt, self::ITERATIONS, 32, true);

        return implode('$', ['pbkdf2_sha256', self::ITERATIONS, base64_encode($salt), base64_encode($hash)]);
    }

    public static function verify(string $pin, ?string $stored): bool
    {
        if (! $stored) {
            return false;
        }

        $parts = explode('$', $stored);
        if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
            return false;
        }

        $salt = base64_decode($parts[2], true);
        $expected = base64_decode($parts[3], true);
        $iterations = (int) $parts[1];

        if ($salt === false || $expected === false || $iterations < 1) {
            return false;
        }

        return hash_equals($expected, hash_pbkdf2('sha256', $pin, $salt, $iterations, strlen($expected), true));
    }

    /** PIN 6 angka. */
    public static function rules(): array
    {
        return ['required', 'string', 'regex:/^\d{6}$/'];
    }
}
