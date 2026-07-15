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
