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
    foreach (['attendance.view', 'attendance.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['attendance.view', 'attendance.update']);

    return $user;
}

function overtimeDay(int $minutes = 90): array
{
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $date = now()->startOfMonth()->addDays(5)->toDateString();
    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'status' => 'present', 'overtime_minutes' => $minutes,
    ]);

    return [$employee, $date];
}

test('HR approves overtime and it appears in the recap', function () {
    $hr = overtimeHr();
    [$employee, $date] = overtimeDay(90);

    $this->actingAs($hr)->post('/attendance/overtime/approve', [
        'employee_id' => $employee->id,
        'work_date' => $date,
    ])->assertRedirect();

    $approval = OvertimeApproval::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($approval->status)->toBe('approved')
        ->and($approval->approved_minutes)->toBe(90)
        ->and($approval->computed_minutes)->toBe(90);

    $this->actingAs($hr)->get('/attendance/overtime/recap?month='.now()->format('Y-m'))
        ->assertOk()
        ->assertSee('Budi');
});

test('HR can approve a reduced overtime amount', function () {
    $hr = overtimeHr();
    [$employee, $date] = overtimeDay(120);

    $this->actingAs($hr)->post('/attendance/overtime/approve', [
        'employee_id' => $employee->id,
        'work_date' => $date,
        'approved_minutes' => 60,
    ])->assertRedirect();

    expect(OvertimeApproval::query()->where('employee_id', $employee->id)->value('approved_minutes'))->toBe(60);
});

test('HR rejects overtime', function () {
    $hr = overtimeHr();
    [$employee, $date] = overtimeDay(90);

    $this->actingAs($hr)->post('/attendance/overtime/reject', [
        'employee_id' => $employee->id,
        'work_date' => $date,
    ])->assertRedirect();

    $approval = OvertimeApproval::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($approval->status)->toBe('rejected')
        ->and($approval->approved_minutes)->toBe(0);
});

test('the overtime approval and recap pages render', function () {
    $hr = overtimeHr();
    overtimeDay(90);

    $this->actingAs($hr)->get('/attendance/overtime')->assertOk()->assertSee('Persetujuan Lembur');
    $this->actingAs($hr)->get('/attendance/overtime/recap')->assertOk()->assertSee('Rekap Lembur');
});
