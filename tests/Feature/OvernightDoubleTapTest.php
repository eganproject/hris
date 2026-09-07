<?php

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Services\AttendanceResolver;
use App\Services\AttendanceRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Shift malam 22:00–06:00, dijadwalkan HANYA pada 2026-02-10. Tanggal 11 sengaja
 * kosong — itulah keadaan yang paling umum: sesudah bekerja semalaman, orangnya
 * libur.
 *
 * @return array{0: Employee, 1: Carbon}
 */
function nightShiftEmployee(): array
{
    $shift = Shift::query()->create([
        'code' => 'MLM', 'name' => 'Malam', 'start_time' => '22:00', 'end_time' => '06:00',
        'crosses_midnight' => true, 'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 0, 'overtime_min_minutes' => 0, 'is_active' => true,
    ]);

    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-02-10',
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    return [$employee, Carbon::parse('2026-02-10')];
}

test('tap pulang dua kali pada shift malam tidak membuat absensi baru keesokan harinya', function () {
    [$employee, $malam] = nightShiftEmployee();
    $besok = Carbon::parse('2026-02-11');

    $employee->punches()->create(['punched_at' => '2026-02-10 22:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'masuk']);
    // Pulang, di-tap dua kali seperti yang terjadi di lapangan.
    $employee->punches()->create(['punched_at' => '2026-02-11 06:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'pulang-1']);
    $employee->punches()->create(['punched_at' => '2026-02-11 06:00:05', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'pulang-2']);

    // Persis seperti yang dilakukan feed mesin: tiap punch baru menghitung ulang
    // hari itu DAN sehari sebelumnya.
    $rollup = app(AttendanceRollup::class);
    $rollup->rebuild($employee, $malam);
    $rollup->rebuild($employee, $besok);

    $shiftMalam = $employee->attendances()->whereDate('work_date', '2026-02-10')->first();

    expect($shiftMalam?->clock_in?->format('H:i'))->toBe('22:00')
        ->and($shiftMalam?->clock_out?->format('H:i'))->toBe('06:00');

    // Tap pulang itu milik shift malam tanggal 10. Ia tidak boleh muncul lagi sebagai
    // absensi tanggal 11 — apalagi sebagai jam masuk.
    expect($employee->attendances()->whereDate('work_date', '2026-02-11')->exists())->toBeFalse();
});

test('tap pulang sekali pun tidak menumbuhkan absensi di hari berikutnya', function () {
    [$employee, $malam] = nightShiftEmployee();
    $besok = Carbon::parse('2026-02-11');

    $employee->punches()->create(['punched_at' => '2026-02-10 22:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'masuk']);
    $employee->punches()->create(['punched_at' => '2026-02-11 06:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'pulang']);

    $rollup = app(AttendanceRollup::class);
    $rollup->rebuild($employee, $malam);
    $rollup->rebuild($employee, $besok);

    expect($employee->attendances()->whereDate('work_date', '2026-02-11')->exists())->toBeFalse()
        ->and($employee->attendances()->whereDate('work_date', '2026-02-10')->first()?->clock_out?->format('H:i'))->toBe('06:00');
});

test('masuk shift berikutnya tetap jadi absensi hari itu, bukan milik shift malam', function () {
    [$employee, $malam] = nightShiftEmployee();
    $besok = Carbon::parse('2026-02-11');

    // Tanggal 11 dijadwalkan shift siang. Jaraknya wajar dari shift malam yang
    // berakhir 06:00, sehingga tap pulang malam dan tap masuk siang jelas milik hari
    // yang berbeda.
    $siang = Shift::query()->create([
        'code' => 'SIANG', 'name' => 'Siang', 'start_time' => '14:00', 'end_time' => '22:00',
        'crosses_midnight' => false, 'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 0, 'overtime_min_minutes' => 0, 'is_active' => true,
    ]);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-02-11',
        'shift_id' => $siang->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    $employee->punches()->create(['punched_at' => '2026-02-10 22:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'masuk-malam']);
    $employee->punches()->create(['punched_at' => '2026-02-11 05:55:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'pulang-malam']);
    $employee->punches()->create(['punched_at' => '2026-02-11 14:02:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'masuk-siang']);

    $rollup = app(AttendanceRollup::class);
    $rollup->rebuild($employee, $malam);
    $rollup->rebuild($employee, $besok);

    $shiftMalam = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();
    $shiftSiang = $employee->attendances()->whereDate('work_date', '2026-02-11')->firstOrFail();

    expect($shiftMalam->clock_in?->format('H:i'))->toBe('22:00')
        ->and($shiftMalam->clock_out?->format('H:i'))->toBe('05:55')
        // Tap 14:02 tidak tertelan shift malam, dan tap 05:55 tidak bocor ke hari ini.
        ->and($shiftSiang->clock_in?->format('H:i'))->toBe('14:02')
        ->and($shiftSiang->clock_out)->toBeNull();
});

test('penanda masuk/pulang dari mesin dipakai saat ia membedakan', function () {
    [$employee, $malam] = nightShiftEmployee();

    // Tap masuk dua kali (jari tidak terbaca sekali), lalu pulang dua kali.
    $employee->punches()->create(['punched_at' => '2026-02-10 21:58:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'in-1']);
    $employee->punches()->create(['punched_at' => '2026-02-10 22:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'in-2']);
    $employee->punches()->create(['punched_at' => '2026-02-11 06:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'out-1']);
    $employee->punches()->create(['punched_at' => '2026-02-11 06:00:05', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'out-2']);

    app(AttendanceRollup::class)->rebuild($employee, $malam);

    $absensi = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();

    // Masuk paling awal di antara yang bertanda masuk, pulang paling akhir di antara
    // yang bertanda pulang.
    expect($absensi->clock_in?->format('H:i'))->toBe('21:58')
        ->and($absensi->clock_out?->format('H:i'))->toBe('06:00');
});

test('hari yang hanya berisi tap pulang tidak lagi dicatat sebagai jam masuk', function () {
    [$employee, $malam] = nightShiftEmployee();

    // Lupa tap masuk semalam; yang tercatat hanya kepulangannya.
    $employee->punches()->create(['punched_at' => '2026-02-11 06:02:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'out-only']);

    app(AttendanceRollup::class)->rebuild($employee, $malam);

    $absensi = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();

    expect($absensi->clock_in)->toBeNull()
        ->and($absensi->clock_out?->format('H:i'))->toBe('06:02')
        // Jam masuknya memang tidak ada, dan itu yang perlu dikoreksi HR.
        ->and($absensi->status)->toBe(AttendanceStatus::Absent);
});

test('kalau penandanya seragam, urutan waktu yang dipakai seperti semula', function () {
    [$employee, $malam] = nightShiftEmployee();

    // Prosedur terlewat: dua-duanya bertanda masuk.
    $employee->punches()->create(['punched_at' => '2026-02-10 22:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'a']);
    $employee->punches()->create(['punched_at' => '2026-02-11 06:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'b']);

    app(AttendanceRollup::class)->rebuild($employee, $malam);

    $absensi = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();

    expect($absensi->clock_in?->format('H:i'))->toBe('22:00')
        ->and($absensi->clock_out?->format('H:i'))->toBe('06:00');
});

test('tap pulang yang keliru ditekan sebelum masuk tidak membuat kerja sehari penuh', function () {
    $shift = Shift::query()->create([
        'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '17:00',
        'crosses_midnight' => false, 'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 0, 'overtime_min_minutes' => 0, 'is_active' => true,
    ]);
    $employee = Employee::query()->create(['full_name' => 'Siti', 'employment_status' => 'active']);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-02-10',
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    // Salah tekan: penandanya masih "pulang" saat datang, baru dibetulkan lalu tap
    // masuk. Sesudah itu ia lupa tap pulang.
    $employee->punches()->create(['punched_at' => '2026-02-10 07:55:00', 'machine_user_id' => '9', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'salah-tekan']);
    $employee->punches()->create(['punched_at' => '2026-02-10 08:05:00', 'machine_user_id' => '9', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'masuk']);

    app(AttendanceRollup::class)->rebuild($employee, Carbon::parse('2026-02-10'));

    $absensi = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();

    // Kalau penandanya dipercaya mentah-mentah, jam pulang 07:55 akan digulirkan ke
    // hari berikutnya dan menghasilkan rentang kerja hampir 24 jam.
    expect($absensi->work_minutes)->toBeLessThan(600)
        ->and($absensi->clock_in?->format('H:i'))->toBe('07:55')
        ->and($absensi->clock_out?->format('H:i'))->toBe('08:05');
});

test('jam masuk hasil koreksi tidak ditutup oleh tap kedatangan kedua', function () {
    [$employee, $malam] = nightShiftEmployee();

    // HR membetulkan jam masuk. Karyawannya sempat menempel jari dua kali saat datang
    // karena yang pertama tidak terbaca, lalu tap pulang seperti biasa.
    app(AttendanceResolver::class)->resolve($employee, $malam, '22:00', null, 'Koreksi disetujui.');

    $employee->punches()->create(['punched_at' => '2026-02-10 22:03:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 0, 'dedup_hash' => 'masuk-ulang']);
    $employee->punches()->create(['punched_at' => '2026-02-11 06:01:00', 'machine_user_id' => '17', 'status' => 'matched', 'state' => 1, 'dedup_hash' => 'pulang']);

    app(AttendanceRollup::class)->rebuild($employee, $malam);

    $absensi = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();

    expect($absensi->clock_in?->format('H:i'))->toBe('22:00')
        // Bukan 22:03 — tap itu bertanda kedatangan, bukan kepulangan.
        ->and($absensi->clock_out?->format('H:i'))->toBe('06:01');
});
