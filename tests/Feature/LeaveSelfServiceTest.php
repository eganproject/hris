<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Employee}
 */
function employeeAccount(?Employee $manager = null, string $name = 'Karyawan'): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-leave.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-leave.view');

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'full_name' => $name,
        'employment_status' => 'active',
        'manager_id' => $manager?->id,
    ]);

    return [$user, $employee];
}

test('the self-service pages render', function () {
    [$user] = employeeAccount(name: 'Budi');

    $this->actingAs($user)->get('/my-leave')->assertOk();
    $this->actingAs($user)->get('/my-leave/create')->assertOk();
});

test('an employee submits leave for themselves and it routes to their supervisor', function () {
    [$bossUser, $boss] = employeeAccount(name: 'Bos');
    [$user, $employee] = employeeAccount(manager: $boss, name: 'Budi');

    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->format('Y-m-d'),
        'end_date' => now()->addDays(4)->format('Y-m-d'),
        'reason' => 'Urusan keluarga',
    ])->assertRedirect(route('my-leave.index'));

    $leave = LeaveRequest::query()->firstOrFail();

    expect($leave->employee_id)->toBe($employee->id)
        ->and($leave->supervisor_id)->toBe($boss->id)
        ->and($leave->status)->toBe(LeaveRequestStatus::PendingSupervisor);
});

test('the supervisor approves a subordinate request from self-service', function () {
    [$bossUser, $boss] = employeeAccount(name: 'Bos');
    [$user, $employee] = employeeAccount(manager: $boss, name: 'Budi');
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->format('Y-m-d'),
        'end_date' => now()->addDays(3)->format('Y-m-d'),
    ])->assertRedirect(route('my-leave.index'));

    $leave = LeaveRequest::query()->firstOrFail();

    // The boss sees & approves it.
    $this->actingAs($bossUser)->patch(route('my-leave.approve', $leave))->assertRedirect(route('my-leave.index'));
    expect($leave->refresh()->status)->toBe(LeaveRequestStatus::PendingHr);

    // A different employee cannot act on it.
    [$otherUser] = employeeAccount(name: 'Orang Lain');
    $this->actingAs($otherUser)->patch(route('my-leave.approve', $leave))->assertForbidden();
});

test('an employee can cancel their own pending request but not approve it', function () {
    [$user, $employee] = employeeAccount(name: 'Budi');
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->format('Y-m-d'),
        'end_date' => now()->addDays(3)->format('Y-m-d'),
    ])->assertRedirect(route('my-leave.index'));

    $leave = LeaveRequest::query()->firstOrFail();

    $this->actingAs($user)->patch(route('my-leave.cancel', $leave))->assertRedirect(route('my-leave.index'));
    expect($leave->refresh()->status)->toBe(LeaveRequestStatus::Cancelled);
});

test('requesting more than the remaining quota is rejected', function () {
    [$user, $employee] = employeeAccount(name: 'Budi');
    $type = LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 2, 'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from(route('my-leave.create'))
        ->post('/my-leave', [
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(3)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'), // 5 days > quota 2
        ])
        ->assertRedirect(route('my-leave.create'))
        ->assertSessionHasErrors('leave_type_id');

    expect(LeaveRequest::query()->count())->toBe(0);
});

test('an account not linked to an employee cannot use self-service', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-leave.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('my-leave.view');

    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'end_date' => now()->addDay()->format('Y-m-d'),
    ])->assertForbidden();
});
