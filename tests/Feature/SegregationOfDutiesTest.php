<?php

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** An HR user who is ALSO an employee (so they can file their own requests). */
function hrEmployee(array $extra = []): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['leave.view', 'leave.update', 'corrections.view', 'corrections.update', 'attendance.view.all', 'my-leave.view', ...$extra] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['leave.view', 'leave.update', 'corrections.view', 'corrections.update', 'attendance.view.all', ...$extra]);
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'HR Sendiri', 'employment_status' => 'active']);

    return [$user, $employee];
}

test('HR cannot approve or reject their own leave request', function () {
    [$user, $employee] = hrEmployee();
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    // Diajukan atas nama dirinya sendiri, langsung ke tahap HR (tanpa atasan).
    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/approve")->assertForbidden();
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/reject")->assertForbidden();
    expect($leave->fresh()->status)->toBe(LeaveRequestStatus::PendingHr);
});

test('another HR can approve it — the rule only blocks self-decision', function () {
    [, $employee] = hrEmployee();
    [$otherHr] = hrEmployee();
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    $this->actingAs($otherHr)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect();
    expect($leave->fresh()->status)->toBe(LeaveRequestStatus::Approved);
});

test('HR cannot decide their own attendance correction', function () {
    [$user, $employee] = hrEmployee();

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $employee->id, 'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00', 'reason' => 'Lupa absen.', 'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch("/attendance/corrections/{$correction->id}/approve")->assertForbidden();
    $this->actingAs($user)->patch("/attendance/corrections/{$correction->id}/reject")->assertForbidden();
    expect($correction->fresh()->status)->toBe(AttendanceCorrection::STATUS_PENDING);
});

test('a self-managed employee cannot approve their own overtime', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-overtime.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-overtime.view');
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Mandiri', 'employment_status' => 'active']);
    // manager_id menunjuk dirinya sendiri (mis. dari impor) — tetap tidak boleh.
    $employee->update(['manager_id' => $employee->id]);

    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $employee->id, 'supervisor_id' => $employee->id,
        'work_date' => now()->subDay()->toDateString(), 'start_time' => '17:00', 'end_time' => '19:00',
        'requested_minutes' => 120, 'reason' => 'Lembur.', 'requested_at' => now(),
        'computed_minutes' => 0, 'approved_minutes' => 0, 'status' => OvertimeApproval::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch("/my-overtime/{$overtime->id}/approve")->assertForbidden();
    expect($overtime->fresh()->status)->toBe(OvertimeApproval::STATUS_PENDING);
});

test('HR cannot file a leave request for an inactive employee', function () {
    [$user] = hrEmployee(['leave.create']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);
    $inactive = Employee::query()->create(['full_name' => 'Sudah Keluar', 'employment_status' => 'inactive']);

    $this->actingAs($user)
        ->from('/attendance/leave/create')
        ->post('/attendance/leave', [
            'employee_id' => $inactive->id, 'leave_type_id' => $type->id,
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        ])
        ->assertRedirect('/attendance/leave/create')
        ->assertSessionHasErrors('employee_id');

    expect(LeaveRequest::query()->count())->toBe(0);
});
