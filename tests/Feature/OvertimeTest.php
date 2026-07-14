<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function overtimeHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['attendance.view', 'attendance.view.all', 'attendance.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.view.all', 'attendance.update']);

    return $user;
}

/**
 * A subordinate (with a login) plus their supervisor (with a login), and an
 * attendance row on the overtime day so the computed minutes have a source.
 *
 * @return array{0: User, 1: Employee, 2: User, 3: string}
 */
function overtimeStaff(int $computedMinutes = 90): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('overtime.request', 'web');

    $supervisorUser = User::factory()->create();
    $supervisorUser->givePermissionTo('overtime.request');
    $supervisor = Employee::query()->create([
        'user_id' => $supervisorUser->id, 'full_name' => 'Sari Atasan', 'employment_status' => 'active',
    ]);

    $employeeUser = User::factory()->create();
    $employeeUser->givePermissionTo('overtime.request');
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id, 'full_name' => 'Budi', 'employment_status' => 'active',
        'manager_id' => $supervisor->id,
    ]);

    $date = now()->startOfMonth()->addDays(5)->toDateString();

    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'status' => 'present', 'overtime_minutes' => $computedMinutes,
    ]);

    return [$employeeUser, $employee, $supervisorUser, $date];
}

/** The employee files the request their supervisor then has to decide on. */
function requestOvertime(User $employeeUser, string $date, string $end = '18:30'): OvertimeApproval
{
    test()->actingAs($employeeUser)->post('/my-overtime', [
        'work_date' => $date,
        'start_time' => '17:00',
        'end_time' => $end,
        'reason' => 'Kejar target produksi.',
    ])->assertRedirect('/my-overtime');

    return OvertimeApproval::query()->latest('id')->firstOrFail();
}

test('the supervisor approves overtime and it appears in the HR recap', function () {
    [$employeeUser, $employee, $supervisorUser, $date] = overtimeStaff(90);

    $overtime = requestOvertime($employeeUser, $date); // 17:00–18:30 = 90 menit

    $this->actingAs($supervisorUser)
        ->patch("/my-overtime/{$overtime->id}/approve")
        ->assertRedirect('/my-overtime');

    $overtime->refresh();

    expect($overtime->status)->toBe('approved')
        ->and($overtime->approved_minutes)->toBe(90)
        ->and($overtime->computed_minutes)->toBe(90)
        ->and($overtime->employee_id)->toBe($employee->id);

    $this->actingAs(overtimeHr())->get('/attendance/overtime/recap?month='.now()->format('Y-m'))
        ->assertOk()
        ->assertSee('Budi');
});

test('the supervisor can approve a reduced overtime amount', function () {
    [$employeeUser, , $supervisorUser, $date] = overtimeStaff(120);

    $overtime = requestOvertime($employeeUser, $date, '19:00'); // 120 menit diajukan

    $this->actingAs($supervisorUser)
        ->patch("/my-overtime/{$overtime->id}/approve", ['approved_minutes' => 60])
        ->assertRedirect('/my-overtime');

    expect($overtime->fresh()->approved_minutes)->toBe(60);
});

test('the supervisor rejects overtime', function () {
    [$employeeUser, , $supervisorUser, $date] = overtimeStaff(90);

    $overtime = requestOvertime($employeeUser, $date);

    $this->actingAs($supervisorUser)
        ->patch("/my-overtime/{$overtime->id}/reject", ['notes' => 'Tidak ada perintah lembur.'])
        ->assertRedirect('/my-overtime');

    $overtime->refresh();

    expect($overtime->status)->toBe('rejected')
        ->and($overtime->approved_minutes)->toBe(0)
        ->and($overtime->notes)->toBe('Tidak ada perintah lembur.');
});

test('only the supervisor named on the request can decide it', function () {
    [$employeeUser, , , $date] = overtimeStaff(90);
    $overtime = requestOvertime($employeeUser, $date);

    // The requester is not their own supervisor.
    $this->actingAs($employeeUser)
        ->patch("/my-overtime/{$overtime->id}/approve")
        ->assertForbidden();

    expect($overtime->fresh()->status)->toBe('pending');
});

test('the overtime approval and recap pages render', function () {
    $hr = overtimeHr();
    overtimeStaff(90);

    $this->actingAs($hr)->get('/attendance/overtime')->assertOk()->assertSee('Persetujuan Lembur');
    $this->actingAs($hr)->get('/attendance/overtime/recap')->assertOk()->assertSee('Rekap Lembur');
});
