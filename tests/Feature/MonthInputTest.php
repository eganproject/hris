<?php

use App\Support\MonthInput;
use Illuminate\Support\Carbon;

/**
 * Penyaring bulan pernah memakai Carbon::createFromFormat('Y-m', ...), yang mengisi
 * bagian tanggal dengan tanggal HARI INI. Dibuka pada tanggal 29–31, bulan pendek
 * meluber ke bulan berikutnya — halaman jadwal dan laporan diam-diam menampilkan bulan
 * yang salah, dan tombol Generate menulis ke sana.
 */
afterEach(fn () => Carbon::setTestNow());

test('a short month resolves correctly even when today is the 29th or later', function (string $today) {
    Carbon::setTestNow(Carbon::parse($today));

    expect(MonthInput::resolve('2026-02')->toDateString())->toBe('2026-02-01');
})->with(['2026-08-29', '2026-08-30', '2026-08-31', '2026-01-31']);

test('an ordinary month resolves to its first day', function () {
    expect(MonthInput::resolve('2026-07')->toDateString())->toBe('2026-07-01')
        ->and(MonthInput::resolve('2026-12')->toDateString())->toBe('2026-12-01');
});

test('empty or malformed input falls back to the current month', function (?string $value) {
    Carbon::setTestNow(Carbon::parse('2026-08-29'));

    expect(MonthInput::resolve($value)->toDateString())->toBe('2026-08-01');
})->with([null, '', 'bulan-ini', '2026', '2026-13', '2026-00', '20260-2']);
