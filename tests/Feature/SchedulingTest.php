<?php

use App\Enums\ScheduleSource;
use App\Enums\SchedulePatternType;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use App\Services\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function scheduleManager(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function weeklyPattern(int $regShiftId): SchedulePattern
{
    $pattern = SchedulePattern::query()->create([
        'code' => 'W', 'name' => 'Weekly', 'type' => SchedulePatternType::FixedWeekly, 'cycle_length' => 7, 'is_active' => true,
    ]);

    // dayOfWeek: 0=Sun..6=Sat. Work Mon-Fri, off weekends.
    foreach ([0 => null, 1 => $regShiftId, 2 => $regShiftId, 3 => $regShiftId, 4 => $regShiftId, 5 => $regShiftId, 6 => null] as $index => $shiftId) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shiftId]);
    }

    return $pattern;
}

test('the generator materializes a fixed weekly pattern with days off', function () {
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-07', // Sun..Sat
    ]);

    $written = app(ScheduleGenerator::class)->forAssignment($assignment);

    expect($written)->toBe(7)
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(7);

    $sunday = EmployeeSchedule::query()->where('work_date', '2026-02-01')->first();
    $monday = EmployeeSchedule::query()->where('work_date', '2026-02-02')->first();

    expect($sunday->is_day_off)->toBeTrue()
        ->and($sunday->shift_id)->toBeNull()
        ->and($monday->is_day_off)->toBeFalse()
        ->and($monday->shift_id)->toBe($reg->id)
        ->and($monday->source)->toBe(ScheduleSource::Generated);
});

test('the generator follows a rotating cycle from its anchor date', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'crosses_midnight' => true, 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Ani', 'employment_status' => 'active']);

    $pattern = SchedulePattern::query()->create([
        'code' => 'R', 'name' => 'Rotasi', 'type' => SchedulePatternType::Rotating, 'cycle_length' => 4, 'anchor_date' => '2026-01-01', 'is_active' => true,
    ]);
    foreach ([0 => $pagi->id, 1 => $siang->id, 2 => $malam->id, 3 => null] as $index => $shiftId) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shiftId]);
    }

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-05',
    ]);

    app(ScheduleGenerator::class)->forAssignment($assignment);

    $shiftOn = fn (string $date) => EmployeeSchedule::query()->where('work_date', $date)->first();

    expect($shiftOn('2026-01-01')->shift_id)->toBe($pagi->id)   // slot 0
        ->and($shiftOn('2026-01-02')->shift_id)->toBe($siang->id) // slot 1
        ->and($shiftOn('2026-01-03')->shift_id)->toBe($malam->id) // slot 2
        ->and($shiftOn('2026-01-04')->is_day_off)->toBeTrue()      // slot 3 = off
        ->and($shiftOn('2026-01-05')->shift_id)->toBe($pagi->id);  // wraps to slot 0
});

test('a manual override is never clobbered when the roster is regenerated', function () {
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $extra = Shift::query()->create(['code' => 'EXT', 'name' => 'Ekstra', 'start_time' => '10:00', 'end_time' => '19:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-07',
    ]);

    $generator = app(ScheduleGenerator::class);
    $generator->forAssignment($assignment);

    // Override the Monday to a different shift, manually.
    $generator->override($employee, \Illuminate\Support\Carbon::parse('2026-02-02'), $extra->id, false, 'Tukar shift');

    // Regenerate the whole window.
    $generator->forEmployee($employee, \Illuminate\Support\Carbon::parse('2026-02-01'), \Illuminate\Support\Carbon::parse('2026-02-07'));

    $monday = EmployeeSchedule::query()->where('work_date', '2026-02-02')->first();

    expect($monday->shift_id)->toBe($extra->id)
        ->and($monday->source)->toBe(ScheduleSource::Manual)
        ->and($monday->note)->toBe('Tukar shift');
});

test('assigning a pattern via the controller generates the schedule', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $this->actingAs($user)->post('/attendance/schedules/assign', [
        'employee_ids' => [$employee->id],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
    ])->assertRedirect();

    expect(ScheduleAssignment::query()->where('employee_id', $employee->id)->exists())->toBeTrue()
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(28);
});

test('storing a pattern persists its slots', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    $this->actingAs($user)->post('/attendance/schedule-patterns', [
        'code' => 'OFF5',
        'name' => 'Kantor',
        'type' => 'fixed_weekly',
        'is_active' => '1',
        'days' => [1 => $reg->id, 2 => $reg->id, 3 => $reg->id, 4 => $reg->id, 5 => $reg->id],
    ])->assertRedirect('/attendance/schedule-patterns');

    $pattern = SchedulePattern::query()->firstWhere('code', 'OFF5');

    expect($pattern->days()->count())->toBe(7)
        ->and($pattern->days()->where('day_index', 1)->value('shift_id'))->toBe($reg->id)
        ->and($pattern->days()->where('day_index', 0)->value('shift_id'))->toBeNull();
});

test('a manual override can be set through the controller', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    $this->actingAs($user)->post('/attendance/schedules/override', [
        'employee_id' => $employee->id,
        'work_date' => '2026-02-10',
        'shift_id' => $reg->id,
        'note' => 'Ganti',
    ])->assertRedirect();

    $row = EmployeeSchedule::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->first();

    expect($row)->not->toBeNull()
        ->and($row->shift_id)->toBe($reg->id)
        ->and($row->source)->toBe(ScheduleSource::Manual);
});

test('scheduling pages render', function () {
    $user = scheduleManager();

    foreach ([
        '/attendance/schedule-patterns',
        '/attendance/schedule-patterns/create',
        '/attendance/schedules',
        '/attendance/schedules/assign',
    ] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});
