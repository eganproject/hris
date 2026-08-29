<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Menerjemahkan parameter penyaring bulan ("2026-02") jadi awal bulan.
 *
 * Dulu tiap halaman menyalin `Carbon::createFromFormat('Y-m', $value)` sendiri, dan
 * itu keliru: createFromFormat mengisi bagian tanggal yang tidak ada dengan tanggal
 * HARI INI. Dibuka pada tanggal 29–31, "2026-02" jadi 29 Februari — yang tidak ada —
 * lalu meluber ke 1 Maret. Halaman jadwal dan laporan diam-diam menampilkan bulan
 * berikutnya, dan tombol Generate pun menulis ke bulan yang salah.
 *
 * Karena itu tanggalnya disusun eksplisit, bukan diserahkan ke parser.
 */
class MonthInput
{
    /** Awal bulan dari "YYYY-MM"; masukan kosong atau tidak masuk akal jatuh ke bulan berjalan. */
    public static function resolve(?string $value): Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', (string) $value, $matches)) {
            return Carbon::now()->startOfMonth();
        }

        $month = (int) $matches[2];

        // Bulan 00 atau 13 juga akan meluber diam-diam kalau diteruskan ke Carbon.
        if ($month < 1 || $month > 12) {
            return Carbon::now()->startOfMonth();
        }

        return Carbon::create((int) $matches[1], $month, 1)->startOfMonth();
    }
}
