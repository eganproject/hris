<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** A user who can see every location's data, with the given menu-view permissions. */
function viewer(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ([...$permissions, 'attendance.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo([...$permissions, 'attendance.view.all']);

    return $user;
}

test('the leave list filters by branch, type, status and date range', function () {
    $user = viewer(['leave.view']);

    $branchA = Branch::query()->create(['code' => 'A', 'name' => 'Cabang A', 'is_active' => true]);
    $branchB = Branch::query()->create(['code' => 'B', 'name' => 'Cabang B', 'is_active' => true]);
    $izin = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);
    $sakit = LeaveType::query()->create(['code' => 'SK', 'name' => 'Sakit', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $andi = Employee::query()->create(['full_name' => 'Andi Cabang A', 'employment_status' => 'active', 'branch_id' => $branchA->id]);
    $bela = Employee::query()->create(['full_name' => 'Bela Cabang B', 'employment_status' => 'active', 'branch_id' => $branchB->id]);

    $leaveA = LeaveRequest::query()->create([
        'employee_id' => $andi->id, 'leave_type_id' => $izin->id,
        'start_date' => '2026-05-10', 'end_date' => '2026-05-11',
        'status' => LeaveRequestStatus::Approved->value,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $bela->id, 'leave_type_id' => $sakit->id,
        'start_date' => '2026-06-20', 'end_date' => '2026-06-21',
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    // Branch filter: only Andi (Cabang A).
    $this->actingAs($user)->get(route('attendance.leave.index', ['branch_id' => $branchA->id]))
        ->assertOk()->assertSee('Andi Cabang A')->assertDontSee('Bela Cabang B');

    // Type filter: only Sakit → Bela.
    $this->actingAs($user)->get(route('attendance.leave.index', ['leave_type_id' => $sakit->id]))
        ->assertOk()->assertSee('Bela Cabang B')->assertDontSee('Andi Cabang A');

    // Status filter: only approved → Andi.
    $this->actingAs($user)->get(route('attendance.leave.index', ['status' => LeaveRequestStatus::Approved->value]))
        ->assertOk()->assertSee('Andi Cabang A')->assertDontSee('Bela Cabang B');

    // Date range: May only → Andi.
    $this->actingAs($user)->get(route('attendance.leave.index', ['date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
        ->assertOk()->assertSee('Andi Cabang A')->assertDontSee('Bela Cabang B');
});

test('the daily board filters by division, status and search', function () {
    $user = viewer(['attendance-daily.view']);

    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $fin = Department::query()->create(['code' => 'FIN', 'name' => 'Keuangan', 'is_active' => true]);

    $opsEmp = Employee::query()->create(['full_name' => 'Operasional Orang', 'employment_status' => 'active', 'department_id' => $ops->id]);
    $opsEmp->departments()->sync([$ops->id]);
    $finEmp = Employee::query()->create(['full_name' => 'Keuangan Kirana', 'employment_status' => 'active', 'department_id' => $fin->id]);
    $finEmp->departments()->sync([$fin->id]);

    // Division filter: only Operasional.
    $this->actingAs($user)->get(route('attendance.daily.index', ['department_id' => $ops->id]))
        ->assertOk()->assertSee('Operasional Orang')->assertDontSee('Keuangan Kirana');

    // Search by name.
    $this->actingAs($user)->get(route('attendance.daily.index', ['search' => 'Kirana']))
        ->assertOk()->assertSee('Keuangan Kirana')->assertDontSee('Operasional Orang');
});

test('the overtime monitor filters by status and division', function () {
    $user = viewer(['overtime.view']);

    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $fin = Department::query()->create(['code' => 'FIN', 'name' => 'Keuangan', 'is_active' => true]);

    $opsEmp = Employee::query()->create(['full_name' => 'Operasional Otto', 'employment_status' => 'active', 'department_id' => $ops->id]);
    $opsEmp->departments()->sync([$ops->id]);
    $finEmp = Employee::query()->create(['full_name' => 'Keuangan Kevin', 'employment_status' => 'active', 'department_id' => $fin->id]);
    $finEmp->departments()->sync([$fin->id]);

    $month = now()->format('Y-m');
    $day = now()->startOfMonth()->addDays(4)->toDateString();

    OvertimeApproval::query()->create([
        'employee_id' => $opsEmp->id, 'supervisor_id' => $opsEmp->id, 'work_date' => $day,
        'start_time' => '17:00', 'end_time' => '19:00', 'requested_minutes' => 120,
        'reason' => 'x', 'requested_at' => now(), 'computed_minutes' => 0,
        'approved_minutes' => 120, 'status' => OvertimeApproval::STATUS_APPROVED,
    ]);
    OvertimeApproval::query()->create([
        'employee_id' => $finEmp->id, 'supervisor_id' => $finEmp->id, 'work_date' => $day,
        'start_time' => '17:00', 'end_time' => '19:00', 'requested_minutes' => 120,
        'reason' => 'x', 'requested_at' => now(), 'computed_minutes' => 0,
        'approved_minutes' => 0, 'status' => OvertimeApproval::STATUS_PENDING,
    ]);

    // Division filter.
    $this->actingAs($user)->get(route('attendance.overtime.index', ['month' => $month, 'department_id' => $ops->id]))
        ->assertOk()->assertSee('Operasional Otto')->assertDontSee('Keuangan Kevin');

    // Status filter: only pending → Kevin.
    $this->actingAs($user)->get(route('attendance.overtime.index', ['month' => $month, 'status' => OvertimeApproval::STATUS_PENDING]))
        ->assertOk()->assertSee('Keuangan Kevin')->assertDontSee('Operasional Otto');
});
