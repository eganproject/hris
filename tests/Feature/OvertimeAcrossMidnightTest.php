<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Services\AttendanceResolver;
use App\Services\AttendanceRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function dayShift(): Shift
{
    return Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'crosses_midnight' => false, 'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 0, 'overtime_min_minutes' => 0, 'is_active' => true,
    ]);
}

test('compute handles a day-shift clock-out that crosses midnight', function () {
    $shift = dayShift();
    $employee = Employee::query()->create(['full_name' => 'Lembur Malam', 'employment_status' => 'active']);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-02-10',
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    // Masuk 08:00, pulang 01:00 keesokan harinya (lembur).
    $attendance = app(AttendanceResolver::class)->resolve($employee, Carbon::parse('2026-02-10'), '08:00', '01:00');

    // 08:00 → 01:00(+1) = 17 jam, dikurangi istirahat 60' = 960 menit kerja.
    expect($attendance->work_minutes)->toBe(960)
        // Lembur = dari 17:00 sampai 01:00(+1) = 8 jam = 480 menit.
        ->and($attendance->overtime_minutes)->toBe(480)
        ->and($attendance->status)->toBe(AttendanceStatus::Present);
});

test('a machine clock-out past midnight is still owned by the shift day', function () {
    $shift = dayShift();
    $employee = Employee::query()->create(['full_name' => 'Lembur Mesin', 'employment_status' => 'active']);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-02-10',
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    // Punch mesin: masuk 08:00 tgl 10, pulang 01:00 tgl 11 (lembur lewat tengah malam).
    $employee->punches()->create(['punched_at' => '2026-02-10 08:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'in-1']);
    $employee->punches()->create(['punched_at' => '2026-02-11 01:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'out-1']);

    // Rollup untuk tanggal shift harus menangkap punch pulang yang lewat tengah malam.
    app(AttendanceRollup::class)->rebuild($employee, Carbon::parse('2026-02-10'));

    $attendance = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->firstOrFail();

    expect($attendance->clock_in->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out?->format('Y-m-d H:i'))->toBe('2026-02-11 01:00')
        ->and($attendance->overtime_minutes)->toBe(480);
});

test('the extended window does not steal the next day early clock-in', function () {
    $shift = dayShift();
    $employee = Employee::query()->create(['full_name' => 'Dua Hari', 'employment_status' => 'active']);

    foreach (['2026-02-10', '2026-02-11'] as $date) {
        EmployeeSchedule::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
        ]);
    }

    // Tgl 10: masuk 08:00, lembur pulang 01:00 tgl 11. Tgl 11: masuk pagi 07:55.
    $employee->punches()->create(['punched_at' => '2026-02-10 08:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'a']);
    $employee->punches()->create(['punched_at' => '2026-02-11 01:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'b']);
    $employee->punches()->create(['punched_at' => '2026-02-11 07:55:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'c']);
    $employee->punches()->create(['punched_at' => '2026-02-11 17:03:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'd']);

    $rollup = app(AttendanceRollup::class);
    $rollup->rebuild($employee, Carbon::parse('2026-02-10'));
    $rollup->rebuild($employee, Carbon::parse('2026-02-11'));

    $day10 = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->firstOrFail();
    $day11 = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-11')->firstOrFail();

    // Tgl 10 memiliki punch lemburnya; tgl 11 memiliki punch paginya sendiri.
    expect($day10->clock_out?->format('Y-m-d H:i'))->toBe('2026-02-11 01:00')
        ->and($day11->clock_in->format('H:i'))->toBe('07:55')
        ->and($day11->clock_out->format('H:i'))->toBe('17:03');
});
