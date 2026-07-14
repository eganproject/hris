<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Employee}
 */
function correctionEmployee(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('attendance.correction', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('attendance.correction');
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Budi', 'employment_status' => 'active']);

    return [$user, $employee];
}

function correctionHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['attendance.view', 'attendance.view.all', 'attendance.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.view.all', 'attendance.update']);

    return $user;
}

test('an employee submits an attendance correction for themselves', function () {
    [$user, $employee] = correctionEmployee();

    $this->actingAs($user)->post('/my-attendance/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa tap saat pulang.',
    ])->assertRedirect(route('my-attendance.index'));

    expect(AttendanceCorrection::query()->where('employee_id', $employee->id)->where('status', 'pending')->count())->toBe(1);
});

test('a correction with no requested time is rejected', function () {
    [$user] = correctionEmployee();

    $this->actingAs($user)
        ->from(route('my-attendance.index'))
        ->post('/my-attendance/corrections', [
            'work_date' => now()->subDay()->toDateString(),
            'reason' => 'Salah',
        ])
        ->assertSessionHasErrors('requested_clock_in');
});

test('HR approves a correction and the attendance is updated', function () {
    [, $employee] = correctionEmployee();
    $hr = correctionHr();

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $employee->id,
        'work_date' => '2026-02-10',
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa tap',
        'status' => 'pending',
    ]);

    $this->actingAs($hr)->patch(route('attendance.corrections.approve', $correction))->assertRedirect();

    expect($correction->fresh()->status)->toBe('approved')
        ->and($correction->fresh()->reviewed_by)->toBe($hr->id);

    $attendance = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->firstOrFail();
    expect($attendance->clock_in->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out->format('H:i'))->toBe('17:00');
});

test('HR rejects a correction', function () {
    [, $employee] = correctionEmployee();
    $hr = correctionHr();

    $correction = AttendanceCorrection::query()->create(['employee_id' => $employee->id, 'work_date' => '2026-02-10', 'requested_clock_in' => '08:00', 'reason' => 'x', 'status' => 'pending']);

    $this->actingAs($hr)->patch(route('attendance.corrections.reject', $correction), ['decision_notes' => 'Tidak valid'])->assertRedirect();

    expect($correction->fresh()->status)->toBe('rejected')
        ->and($correction->fresh()->decision_notes)->toBe('Tidak valid')
        ->and(Attendance::query()->count())->toBe(0);
});

test('an employee can cancel their own pending correction', function () {
    [$user, $employee] = correctionEmployee();
    $correction = AttendanceCorrection::query()->create(['employee_id' => $employee->id, 'work_date' => '2026-02-10', 'requested_clock_in' => '08:00', 'reason' => 'x', 'status' => 'pending']);

    $this->actingAs($user)->delete(route('my-attendance.corrections.cancel', $correction))->assertRedirect();

    expect(AttendanceCorrection::query()->count())->toBe(0);
});

test('the self-service and review pages render', function () {
    [$user] = correctionEmployee();
    $this->actingAs($user)->get('/my-attendance')->assertOk();

    $hr = correctionHr();
    $this->actingAs($hr)->get('/attendance/corrections')->assertOk();
});
