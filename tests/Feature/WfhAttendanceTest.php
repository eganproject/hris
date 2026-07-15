<?php

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Seorang karyawan (dengan akun login) yang disetujui WFH hari ini, plus shift
 * reguler pada tanggal itu.
 *
 * @return array{user: User, employee: Employee, shift: Shift, date: \Illuminate\Support\Carbon}
 */
function wfhFixture(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-attendance.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-attendance.view');
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Budi WFH', 'employment_status' => 'active',
    ]);

    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);

    $date = now();
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date->toDateString(),
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    $wfhType = LeaveType::query()->create([
        'code' => 'WFH', 'name' => 'Work From Home', 'attendance_status' => 'wfh',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $wfhType->id,
        'start_date' => $date->toDateString(), 'end_date' => $date->toDateString(),
        'reason' => 'WFH hari ini.', 'status' => \App\Enums\LeaveRequestStatus::Approved->value,
    ]);

    return compact('user', 'employee', 'shift', 'date');
}

test('a WFH day with clock times counts as worked hours, still labelled WFH', function () {
    ['employee' => $employee, 'date' => $date] = wfhFixture();

    // Masuk 08:00, pulang 18:30 (1,5 jam lewat jam pulang → lembur).
    $attendance = app(AttendanceResolver::class)->resolve($employee, $date, '08:00', '18:30');

    expect($attendance->status)->toBe(AttendanceStatus::Wfh)
        // 08:00–18:30 = 630 menit, dikurangi istirahat 60 → 570 menit kerja.
        ->and($attendance->work_minutes)->toBe(570)
        ->and($attendance->overtime_minutes)->toBeGreaterThan(0);
});

test('a WFH day without a check-in is WFH with zero hours, never Absent', function () {
    ['employee' => $employee, 'date' => $date] = wfhFixture();

    $attendance = app(AttendanceResolver::class)->resolve($employee, $date);

    expect($attendance->status)->toBe(AttendanceStatus::Wfh)
        ->and($attendance->work_minutes)->toBe(0);
});

test('the WFH self check-in records the clock-in and clock-out from the app', function () {
    ['user' => $user, 'employee' => $employee] = wfhFixture();

    $this->actingAs($user)->get('/my-attendance')->assertOk()->assertSee('Absen Masuk');

    $this->actingAs($user)->post('/my-attendance/check-in')->assertRedirect();

    $today = $employee->attendances()->whereDate('work_date', now()->toDateString())->firstOrFail();
    expect($today->status)->toBe(AttendanceStatus::Wfh)
        ->and($today->clock_in)->not->toBeNull();

    // Absen masuk kedua kalinya ditolak.
    $this->actingAs($user)->post('/my-attendance/check-in')->assertRedirect();
    expect($employee->attendances()->whereDate('work_date', now()->toDateString())->count())->toBe(1);

    $this->actingAs($user)->post('/my-attendance/check-out')->assertRedirect();
    expect($today->fresh()->clock_out)->not->toBeNull();
});

test('self check-in is refused on a day that is not approved WFH', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-attendance.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-attendance.view');
    Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Bukan WFH', 'employment_status' => 'active',
    ]);

    $this->actingAs($user)->get('/my-attendance')->assertOk()->assertDontSee('Absen Masuk');

    $this->actingAs($user)->post('/my-attendance/check-in')
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(App\Models\Attendance::query()->count())->toBe(0);
});

test('a scheduled WFH day counts as worked hours without any leave request', function () {
    // Karyawan dijadwalkan WFH (bukan mengajukan) — hari itu tetap bekerja dari rumah.
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'is_active' => true,
    ]);
    $employee = Employee::query()->create(['full_name' => 'Terjadwal WFH', 'employment_status' => 'active']);

    $date = now();
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date->toDateString(),
        'shift_id' => $shift->id, 'is_day_off' => false, 'is_wfh' => true, 'source' => 'generated',
    ]);

    $attendance = app(AttendanceResolver::class)->resolve($employee, $date, '08:00', '17:00');

    expect($attendance->status)->toBe(AttendanceStatus::Wfh)
        ->and($attendance->work_minutes)->toBe(480); // 9 jam - 1 jam istirahat
});

test('the generator carries the WFH flag from a pattern day onto the roster', function () {
    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Hybrid', 'employment_status' => 'active']);

    // Pola mingguan: semua hari kantor, kecuali satu hari WFH.
    $pattern = App\Models\SchedulePattern::query()->create([
        'code' => 'HYB', 'name' => 'Hybrid', 'type' => App\Enums\SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true,
    ]);
    // 2026-02-02 adalah Senin (dayOfWeek 1); jadikan Senin WFH.
    foreach ([0 => null, 1 => $shift->id, 2 => $shift->id, 3 => $shift->id, 4 => $shift->id, 5 => $shift->id, 6 => null] as $index => $shiftId) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shiftId, 'is_wfh' => $index === 1]);
    }

    $assignment = App\Models\ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-02', 'end_date' => '2026-02-03',
    ]);
    app(App\Services\ScheduleGenerator::class)->forAssignment($assignment);

    $monday = $employee->schedules()->whereDate('work_date', '2026-02-02')->firstOrFail();
    $tuesday = $employee->schedules()->whereDate('work_date', '2026-02-03')->firstOrFail();

    expect($monday->is_wfh)->toBeTrue()
        ->and($tuesday->is_wfh)->toBeFalse();
});

test('the daily override can mark a day WFH, and clearing it removes the flag', function () {
    $generator = app(App\Services\ScheduleGenerator::class);
    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Override WFH', 'employment_status' => 'active']);

    $day = $generator->override($employee, now(), $shift->id, false, null, true);
    expect($day->is_wfh)->toBeTrue();

    // Ditandai libur → WFH ikut hilang.
    $off = $generator->override($employee, now(), null, true, null, true);
    expect($off->is_wfh)->toBeFalse();
});

test('non-WFH approved leave still short-circuits to its own status with zero hours', function () {
    ['employee' => $employee, 'date' => $date] = wfhFixture();

    // Ganti pengajuannya jadi Sakit pada tanggal yang sama.
    $sick = LeaveType::query()->create([
        'code' => 'SK', 'name' => 'Sakit', 'attendance_status' => 'sick',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
    $employee->leaveRequests()->update(['leave_type_id' => $sick->id]);

    $attendance = app(AttendanceResolver::class)->resolve($employee, $date, '08:00', '17:00');

    expect($attendance->status)->toBe(AttendanceStatus::Sick)
        ->and($attendance->work_minutes)->toBe(0);
});
