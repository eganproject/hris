<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Services\PunchIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function deviceSchedule(Employee $employee, string $date, Shift $shift): void
{
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);
}

function pushAttlog(string $sn, string $body)
{
    return test()->call('POST', "/iclock/cdata?SN={$sn}&table=ATTLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
}

test('the iclock handshake returns options for a registered device', function () {
    Device::query()->create(['serial_number' => 'X1', 'name' => 'Lobby', 'is_active' => true]);

    $this->get('/iclock/cdata?SN=X1')
        ->assertOk()
        ->assertSee('GET OPTION FROM: X1');

    expect(Device::query()->firstWhere('serial_number', 'X1')->last_seen_at)->not->toBeNull();
});

test('the handshake tells the device its timezone in hours so its clock stays correct', function () {
    // Default (Jakarta) → GMT+7 so the X100-C stops drifting an hour off.
    Device::query()->create(['serial_number' => 'X1', 'name' => 'Lobby', 'is_active' => true, 'timezone' => 'Asia/Jakarta']);
    $this->get('/iclock/cdata?SN=X1')->assertOk()->assertSee('TimeZone=7');

    // A device configured for another zone advertises its own offset (WIT → GMT+9).
    Device::query()->create(['serial_number' => 'X2', 'name' => 'Papua', 'is_active' => true, 'timezone' => 'Asia/Jayapura']);
    $this->get('/iclock/cdata?SN=X2')->assertOk()->assertSee('TimeZone=9');
});

test('an unknown or inactive serial number is rejected', function () {
    $this->get('/iclock/cdata?SN=NOPE')->assertStatus(401);
    pushAttlog('NOPE', "1\t2026-02-10 08:00:00\t0\t1")->assertStatus(401);
});

test('pushed punches for a mapped PIN create punches and a resolved attendance', function () {
    $device = Device::query()->create(['serial_number' => 'X1', 'name' => 'Lobby', 'is_active' => true]);
    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '17']);
    deviceSchedule($employee, '2026-02-10', $shift);

    $body = "17\t2026-02-10 08:00:00\t0\t1\n17\t2026-02-10 17:05:00\t1\t1";
    pushAttlog('X1', $body)->assertOk()->assertSee('OK');

    expect(AttendancePunch::query()->where('employee_id', $employee->id)->where('status', 'matched')->count())->toBe(2);

    $attendance = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->clock_in->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out->format('H:i'))->toBe('17:05');
});

test('re-pushing the same punches is idempotent', function () {
    $device = Device::query()->create(['serial_number' => 'X1', 'name' => 'Lobby', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '17']);

    $body = "17\t2026-02-10 08:00:00\t0\t1";
    pushAttlog('X1', $body);
    pushAttlog('X1', $body);

    expect(AttendancePunch::query()->count())->toBe(1);
});

test('an unmapped PIN is stored unmatched, then back-filled when the PIN is assigned', function () {
    $device = Device::query()->create(['serial_number' => 'X1', 'name' => 'Lobby', 'is_active' => true]);
    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    deviceSchedule($employee, '2026-02-10', $shift);

    pushAttlog('X1', "99\t2026-02-10 08:00:00\t0\t1\n99\t2026-02-10 17:00:00\t1\t1");

    expect(AttendancePunch::query()->where('status', 'unmatched')->count())->toBe(2)
        ->and(Attendance::query()->count())->toBe(0);

    app(PunchIngestionService::class)->assignPin($employee, $device, '99');

    expect(AttendancePunch::query()->where('status', 'matched')->count())->toBe(2)
        ->and(Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->exists())->toBeTrue();
});

test('an overnight shift attributes an after-midnight punch to the previous work date', function () {
    $device = Device::query()->create(['serial_number' => 'X1', 'name' => 'Lobby', 'is_active' => true]);
    $shift = Shift::query()->create(['code' => 'NGT', 'name' => 'Malam', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true, 'break_minutes' => 60, 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '17']);
    deviceSchedule($employee, '2026-02-10', $shift);

    pushAttlog('X1', "17\t2026-02-10 22:00:00\t0\t1\n17\t2026-02-11 06:00:00\t1\t1");

    $attendance = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->work_minutes)->toBe(420); // 8h span - 60m break
});
