<?php

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** A user who sees everything, with the given menu permissions. */
function bulkViewer(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ([...$permissions, 'attendance.view.all', 'employees.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo([...$permissions, 'attendance.view.all', 'employees.view.all']);

    return $user;
}

test('the search endpoint returns scoped employees matching the term', function () {
    $user = bulkViewer(['employees.view']);

    Employee::query()->create(['full_name' => 'Budi Santoso', 'employment_status' => 'active', 'employee_number' => 'COK0726-A0001']);
    Employee::query()->create(['full_name' => 'Siti Aminah', 'employment_status' => 'active', 'employee_number' => 'COK0726-A0002']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Budi']));

    $response->assertOk();
    $names = collect($response->json('employees'))->pluck('name');

    expect($names)->toContain('Budi Santoso')
        ->and($names)->not->toContain('Siti Aminah');
});

test('the search endpoint needs at least two characters', function () {
    $user = bulkViewer(['employees.view']);
    Employee::query()->create(['full_name' => 'Budi Santoso', 'employment_status' => 'active']);

    $this->actingAs($user)->getJson(route('search', ['q' => 'B']))
        ->assertOk()
        ->assertJson(['employees' => []]);
});

test('the search endpoint does not leak employees outside the user scope', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('employees.view', 'web');

    $branchA = Branch::query()->create(['code' => 'A', 'name' => 'Cabang A', 'is_active' => true]);
    $branchB = Branch::query()->create(['code' => 'B', 'name' => 'Cabang B', 'is_active' => true]);

    $user = User::factory()->create();
    $user->givePermissionTo('employees.view');
    Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Atasan A', 'employment_status' => 'active', 'branch_id' => $branchA->id]);

    Employee::query()->create(['full_name' => 'Orang Cabang B', 'employment_status' => 'active', 'branch_id' => $branchB->id]);

    $names = collect($this->actingAs($user)->getJson(route('search', ['q' => 'Orang']))->json('employees'))->pluck('name');

    expect($names)->not->toContain('Orang Cabang B');
});

test('bulk-approving leave requests approves the selected pending ones and skips the rest', function () {
    $user = bulkViewer(['leave.view', 'leave.update']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    // Two employees without a manager → each pending request sits at the HR step.
    $andi = Employee::query()->create(['full_name' => 'Andi', 'employment_status' => 'active']);
    $bela = Employee::query()->create(['full_name' => 'Bela', 'employment_status' => 'active']);

    $leaveAndi = LeaveRequest::query()->create([
        'employee_id' => $andi->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);
    $leaveBela = LeaveRequest::query()->create([
        'employee_id' => $bela->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);
    // Already approved → must stay untouched even if its id is submitted.
    $leaveDone = LeaveRequest::query()->create([
        'employee_id' => $andi->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
        'status' => LeaveRequestStatus::Approved->value,
    ]);

    $this->actingAs($user)->post(route('attendance.leave.bulk-approve'), [
        'ids' => [$leaveAndi->id, $leaveBela->id, $leaveDone->id],
    ])->assertRedirect(route('attendance.leave.index'));

    expect($leaveAndi->fresh()->status)->toBe(LeaveRequestStatus::Approved)
        ->and($leaveBela->fresh()->status)->toBe(LeaveRequestStatus::Approved);
});

test('bulk-approving leave skips a request the approver filed for themselves', function () {
    $user = bulkViewer(['leave.view', 'leave.update']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    // The acting user is also an employee; a request in their own name must not be
    // self-approved via the bulk action.
    $self = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'HR Sendiri', 'employment_status' => 'active']);
    $own = LeaveRequest::query()->create([
        'employee_id' => $self->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    $this->actingAs($user)->post(route('attendance.leave.bulk-approve'), ['ids' => [$own->id]])
        ->assertRedirect(route('attendance.leave.index'));

    expect($own->fresh()->status)->toBe(LeaveRequestStatus::PendingHr);
});

test('bulk-approving corrections applies the selected pending ones', function () {
    $user = bulkViewer(['corrections.view', 'corrections.update']);
    $employee = Employee::query()->create(['full_name' => 'Karyawan Koreksi', 'employment_status' => 'active']);

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $employee->id, 'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00', 'requested_clock_out' => '17:00',
        'reason' => 'Lupa absen.', 'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    $this->actingAs($user)->post(route('attendance.corrections.bulk-approve'), ['ids' => [$correction->id]])
        ->assertRedirect(route('attendance.corrections.index'));

    expect($correction->fresh()->status)->toBe(AttendanceCorrection::STATUS_APPROVED);
});

test('bulk-approve rejects an empty selection', function () {
    $user = bulkViewer(['leave.view', 'leave.update']);

    $this->actingAs($user)->from(route('attendance.leave.index'))
        ->post(route('attendance.leave.bulk-approve'), ['ids' => []])
        ->assertRedirect(route('attendance.leave.index'))
        ->assertSessionHas('error');
});
