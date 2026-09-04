<?php

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Services\AttendanceResolver;
use App\Services\AttendanceRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** @return array{0: Employee, 1: Carbon} */
function correctedDay(): array
{
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'crosses_midnight' => false, 'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 0, 'overtime_min_minutes' => 0, 'is_active' => true,
    ]);

    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-02-10',
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    return [$employee, Carbon::parse('2026-02-10')];
}

test('punch pulang tidak menggusur jam masuk hasil koreksi', function () {
    [$employee, $date] = correctedDay();

    // Karyawan lupa tap masuk; koreksi jam masuk disetujui, jam pulang dikosongkan.
    app(AttendanceResolver::class)->resolve($employee, $date, '08:00', null, 'Koreksi disetujui: lupa tap masuk.');

    // Sorenya ia tap pulang di mesin.
    $employee->punches()->create([
        'punched_at' => '2026-02-10 17:00:00', 'machine_user_id' => '17',
        'status' => 'matched', 'dedup_hash' => 'out-1',
    ]);

    app(AttendanceRollup::class)->rebuild($employee, $date);

    $attendance = $employee->attendances()->firstOrFail();

    expect($attendance->clock_in?->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out?->format('H:i'))->toBe('17:00');
});

test('hari yang murni dari mesin tetap diproses seperti biasa', function () {
    [$employee, $date] = correctedDay();
    $rollup = app(AttendanceRollup::class);

    $employee->punches()->create(['punched_at' => '2026-02-10 08:05:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'a']);
    $rollup->rebuild($employee, $date);

    expect($employee->attendances()->firstOrFail()->clock_in?->format('H:i'))->toBe('08:05')
        ->and($employee->attendances()->firstOrFail()->clock_out)->toBeNull();

    $employee->punches()->create(['punched_at' => '2026-02-10 17:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'b']);
    $rollup->rebuild($employee, $date);

    $attendance = $employee->attendances()->firstOrFail();

    expect($attendance->clock_in?->format('H:i'))->toBe('08:05')
        ->and($attendance->clock_out?->format('H:i'))->toBe('17:00');

    // Tap terakhir tetap yang menang sebagai jam pulang.
    $employee->punches()->create(['punched_at' => '2026-02-10 17:30:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'c']);
    $rollup->rebuild($employee, $date);

    expect($employee->attendances()->firstOrFail()->clock_out?->format('H:i'))->toBe('17:30');
});

test('koreksi yang memperbaiki punch keliru tidak dikembalikan oleh punch berikutnya', function () {
    [$employee, $date] = correctedDay();

    // Tap keliru pukul 05:30 saat baru lewat depan mesin.
    $employee->punches()->create(['punched_at' => '2026-02-10 05:30:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'salah']);
    app(AttendanceRollup::class)->rebuild($employee, $date);

    // HR menyetujui koreksi: jam masuk yang sebenarnya 08:00.
    app(AttendanceResolver::class)->resolve($employee, $date, '08:00', null, 'Koreksi disetujui: tap keliru.');

    // Sorenya ia tap pulang.
    $employee->punches()->create(['punched_at' => '2026-02-10 17:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'pulang']);
    app(AttendanceRollup::class)->rebuild($employee, $date);

    $attendance = $employee->attendances()->firstOrFail();

    expect($attendance->clock_in?->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out?->format('H:i'))->toBe('17:00');
});

test('jam pulang hasil koreksi tidak digusur punch yang datang kemudian', function () {
    [$employee, $date] = correctedDay();

    app(AttendanceResolver::class)->resolve($employee, $date, '08:00', '17:00', 'Koreksi disetujui: lupa tap dua-duanya.');

    $employee->punches()->create(['punched_at' => '2026-02-10 21:45:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'malam']);
    app(AttendanceRollup::class)->rebuild($employee, $date);

    $attendance = $employee->attendances()->firstOrFail();

    expect($attendance->clock_in?->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out?->format('H:i'))->toBe('17:00');
});

test('alasan koreksi tidak ikut hilang saat punch diproses ulang', function () {
    [$employee, $date] = correctedDay();

    app(AttendanceResolver::class)->resolve($employee, $date, '08:00', null, 'Koreksi disetujui: lupa tap masuk.');

    $employee->punches()->create(['punched_at' => '2026-02-10 17:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'out-1']);
    app(AttendanceRollup::class)->rebuild($employee, $date);

    expect($employee->attendances()->firstOrFail()->note)->toBe('Koreksi disetujui: lupa tap masuk.');
});
