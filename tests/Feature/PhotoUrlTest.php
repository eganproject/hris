<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * URL foto harus relatif terhadap root, bukan dibangun dari APP_URL.
 *
 * Dulu ia absolut, jadi foto terpaku ke satu alamat: begitu situs dibuka lewat host,
 * port, atau skema yang berbeda dari APP_URL — https vs http adalah kasus yang paling
 * sering — setiap <img> menunjuk ke tempat lain dan gambarnya tidak muncul, padahal
 * berkasnya ada dan bisa dilayani. Sisa aset halaman memakai asset(), yang mengikuti
 * host permintaan, sehingga halamannya tampak normal dan hanya fotonya yang hilang.
 */
test('the photo url follows whatever host the page is served from', function () {
    config(['app.url' => 'http://alamat-lain.example']);

    $employee = Employee::query()->create([
        'full_name' => 'Budi Santoso',
        'employment_status' => 'active',
        'photo_path' => 'employees/photos/budi.jpg',
    ]);

    expect($employee->photo_url)->toBe('/storage/employees/photos/budi.jpg')
        ->and($employee->photo_url)->not->toStartWith('http');
});

test('an employee without a photo falls back to the company logo', function () {
    $employee = Employee::query()->create([
        'full_name' => 'Tanpa Foto', 'employment_status' => 'active',
    ]);

    expect($employee->photo_url)->toBeNull()
        ->and($employee->photo_display_url)->toContain('company-logo.svg');
});

test('the configured disk url is not pinned to APP_URL', function () {
    // Dijaga di tingkat konfigurasi juga: satu env yang salah di produksi cukup untuk
    // mematikan seluruh foto karyawan sekaligus, dan gejalanya sulit ditebak.
    expect(config('filesystems.disks.public.url'))->toBe('/storage');
});
