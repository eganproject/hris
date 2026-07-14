<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function attendanceActor(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['attendance.view', 'attendance.view.all', 'attendance.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.view.all', 'attendance.update']);

    return $user;
}

function regularShift(): Shift
{
    return Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'crosses_midnight' => false, 'break_minutes' => 60,
        'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 30, 'overtime_min_minutes' => 30, 'is_active' => true,
    ]);
}

function scheduleDay(Employee $employee, string $date, ?Shift $shift): void
{
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'shift_id' => $shift?->id,
        'is_day_off' => $shift === null,
        'source' => 'generated',
    ]);
}

test('an on-time punch on a scheduled shift resolves to Present with overtime', function () {
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-02-10'), '08:00', '18:00');

    expect($result['status'])->toBe(AttendanceStatus::Present)
        ->and($result['late_minutes'])->toBe(0)
        ->and($result['work_minutes'])->toBe(540) // 10h - 60m break
        ->and($result['overtime_minutes'])->toBe(30); // 60m past end - 30m grace
});

test('arriving beyond the tolerance resolves to Late', function () {
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-02-10'), '08:20', '17:00');

    expect($result['status'])->toBe(AttendanceStatus::Late)
        ->and($result['late_minutes'])->toBe(20);
});

test('a scheduled shift with no punch resolves to Absent', function () {
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-02-10'), null, null);

    expect($result['status'])->toBe(AttendanceStatus::Absent);
});

test('a scheduled day off with no punch resolves to DayOff', function () {
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', null);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-02-10'), null, null);

    expect($result['status'])->toBe(AttendanceStatus::DayOff);
});

test('approved leave takes precedence over the scheduled shift', function () {
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $type = LeaveType::query()->create(['code' => 'SK', 'name' => 'Sakit', 'attendance_status' => 'sick', 'is_paid' => true, 'is_active' => true]);
    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => '2026-02-09', 'end_date' => '2026-02-11', 'status' => 'approved',
    ]);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-02-10'), null, null);

    expect($result['status'])->toBe(AttendanceStatus::Sick)
        ->and($result['leave_request_id'])->toBe($leave->id);
});

test('a holiday takes precedence and counts any work as overtime', function () {
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-08-17', $shift);
    $holiday = Holiday::query()->create(['date' => '2026-08-17', 'name' => 'Kemerdekaan', 'is_national' => true]);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-08-17'), '09:00', '12:00');

    expect($result['status'])->toBe(AttendanceStatus::Holiday)
        ->and($result['holiday_id'])->toBe($holiday->id)
        ->and($result['overtime_minutes'])->toBe(180);
});

test('an overnight shift spans midnight when resolving work minutes', function () {
    $shift = Shift::query()->create([
        'code' => 'NGT', 'name' => 'Malam', 'start_time' => '22:00', 'end_time' => '06:00',
        'crosses_midnight' => true, 'break_minutes' => 60, 'is_active' => true,
    ]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-02-10'), '22:00', '06:00');

    expect($result['status'])->toBe(AttendanceStatus::Present)
        ->and($result['work_minutes'])->toBe(420); // 8h span - 60m break
});

test('processing a date persists an Absent row for scheduled-but-unpunched staff', function () {
    $user = attendanceActor();
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $this->actingAs($user)->post('/attendance/daily/process', ['date' => '2026-02-10'])->assertRedirect();

    $attendance = Attendance::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($attendance->status)->toBe(AttendanceStatus::Absent);
});

test('manual punch entry stores and resolves the attendance', function () {
    $user = attendanceActor();
    $shift = regularShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    scheduleDay($employee, '2026-02-10', $shift);

    $this->actingAs($user)->post('/attendance/daily/punch', [
        'employee_id' => $employee->id,
        'work_date' => '2026-02-10',
        'clock_in' => '08:05',
        'clock_out' => '17:00',
    ])->assertRedirect();

    $attendance = Attendance::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->clock_in->format('H:i'))->toBe('08:05');
});

test('the daily attendance page renders', function () {
    $user = attendanceActor();

    $this->actingAs($user)->get('/attendance/daily')->assertOk();
});
