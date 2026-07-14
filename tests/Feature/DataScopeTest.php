<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * A user with the employee permissions but WITHOUT "lihat semua": their data scope
 * decides what they see.
 *
 * @param  list<string>  $extraPermissions
 */
function scopedHr(array $extraPermissions = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = array_merge([
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'employees.export',
        'employees.import',
    ], $extraPermissions);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    // Always registered, so hasPermissionTo() can answer "no" instead of throwing.
    Permission::findOrCreate(User::SCOPE_BYPASS_EMPLOYEES, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * Two locations × two divisions, with one employee in each combination.
 *
 * @return array<string, mixed>
 */
function scopeFixture(): array
{
    $surabaya = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya Office', 'is_active' => true]);
    $jakarta = Branch::query()->create(['code' => 'JKT', 'name' => 'Jakarta Office', 'is_active' => true]);

    $accounting = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);
    $operations = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);

    $position = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staf', 'is_active' => true]);
    $position->departments()->attach([$accounting->id, $operations->id], ['is_active' => true]);

    foreach ([$surabaya, $jakarta] as $branch) {
        $branch->departments()->attach([$accounting->id, $operations->id], ['is_active' => true]);
    }

    $make = fn (Branch $branch, Department $department, string $name) => Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => $name,
        'join_date' => now()->subMonths(3)->toDateString(),
        'employment_status' => 'active',
    ]);

    return [
        'surabaya' => $surabaya,
        'jakarta' => $jakarta,
        'accounting' => $accounting,
        'operations' => $operations,
        'position' => $position,
        'sbyAcc' => $make($surabaya, $accounting, 'Sby Accounting'),
        'sbyOps' => $make($surabaya, $operations, 'Sby Operasional'),
        'jktAcc' => $make($jakarta, $accounting, 'Jkt Accounting'),
    ];
}

/**
 * The attendance-side counterpart of scopedHr(): attendance permissions, but no
 * "attendance.view.all".
 */
function scopedAttendanceHr(array $extraPermissions = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = array_merge(attendanceMenuPermissions(), $extraPermissions);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('attendance, roster and leave pages only show the scoped employees', function () {
    $fixture = scopeFixture();
    $user = scopedAttendanceHr();
    $user->accessBranches()->sync([$fixture['surabaya']->id]);

    $type = LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true,
    ]);

    // One request on each side, so the leave list can show one and hide the other.
    $outsiderLeave = LeaveRequest::query()->create([
        'employee_id' => $fixture['jktAcc']->id, 'leave_type_id' => $type->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'reason' => 'Keperluan keluarga.', 'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    LeaveRequest::query()->create([
        'employee_id' => $fixture['sbyAcc']->id, 'leave_type_id' => $type->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'reason' => 'Keperluan keluarga.', 'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    Attendance::query()->create([
        'employee_id' => $fixture['jktAcc']->id, 'work_date' => now()->toDateString(), 'status' => 'present',
    ]);
    Attendance::query()->create([
        'employee_id' => $fixture['sbyAcc']->id, 'work_date' => now()->toDateString(), 'status' => 'present',
    ]);

    foreach ([
        '/attendance/daily',
        '/attendance/schedules',
        '/attendance/leave',
        '/attendance/leave-balances',
        '/reports/attendance',
        '/reports/attendance-log',
    ] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Sby Accounting')
            ->assertDontSee('Jkt Accounting');
    }

    // And no decision may be made on an outsider's request.
    $this->actingAs($user)->patch("/attendance/leave/{$outsiderLeave->id}/approve")->assertForbidden();
    expect($outsiderLeave->fresh()->status)->toBe(LeaveRequestStatus::PendingHr);
});

test('the schedule and report detail of an employee outside the scope is forbidden', function () {
    $fixture = scopeFixture();
    $user = scopedAttendanceHr();
    $user->accessBranches()->sync([$fixture['surabaya']->id]);

    $inside = $fixture['sbyAcc'];
    $outside = $fixture['jktAcc'];

    $this->actingAs($user)->get("/attendance/schedules/employees/{$inside->id}")->assertOk();
    $this->actingAs($user)->get("/attendance/schedules/employees/{$outside->id}")->assertForbidden();
    $this->actingAs($user)->get("/reports/attendance/{$outside->id}")->assertForbidden();
    $this->actingAs($user)->get("/reports/leave/{$outside->id}")->assertForbidden();
});

test('a user scoped to one location only sees that location', function () {
    $fixture = scopeFixture();
    $user = scopedHr();
    $user->accessBranches()->sync([$fixture['surabaya']->id]);

    $this->actingAs($user)->get('/employees')
        ->assertOk()
        ->assertSee('Sby Accounting')
        ->assertSee('Sby Operasional')
        ->assertDontSee('Jkt Accounting');
});

test('location and division narrow each other', function () {
    $fixture = scopeFixture();
    $user = scopedHr();
    $user->accessBranches()->sync([$fixture['surabaya']->id]);
    $user->accessDepartments()->sync([$fixture['accounting']->id]);

    $this->actingAs($user)->get('/employees')
        ->assertOk()
        ->assertSee('Sby Accounting')
        // Same location but another division, and same division but another location.
        ->assertDontSee('Sby Operasional')
        ->assertDontSee('Jkt Accounting');
});

test('an employee outside the scope cannot be opened, edited or deleted', function () {
    $fixture = scopeFixture();
    $user = scopedHr();
    $user->accessBranches()->sync([$fixture['surabaya']->id]);

    $outsider = $fixture['jktAcc'];

    $this->actingAs($user)->get("/employees/{$outsider->id}")->assertForbidden();
    $this->actingAs($user)->get("/employees/{$outsider->id}/edit")->assertForbidden();
    $this->actingAs($user)->delete("/employees/{$outsider->id}")->assertForbidden();

    expect(Employee::query()->whereKey($outsider->id)->exists())->toBeTrue();
});

test('a scoped user cannot file a new employee into another location', function () {
    $fixture = scopeFixture();
    $user = scopedHr();
    $user->accessBranches()->sync([$fixture['surabaya']->id]);

    $this->actingAs($user)
        ->from('/employees/create')
        ->post('/employees', [
            'branch_id' => $fixture['jakarta']->id,
            'department_id' => $fixture['accounting']->id,
            'job_position_id' => $fixture['position']->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '99']],
            'full_name' => 'Selundupan',
            'join_date' => now()->toDateString(),
            'employment_status' => 'active',
            'contract_number' => 'CTR-SEL',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->toDateString(),
            'contract_end_date' => now()->addYear()->toDateString(),
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees/create')
        ->assertSessionHasErrors('branch_id');

    expect(Employee::query()->where('full_name', 'Selundupan')->exists())->toBeFalse();
});

test('the "lihat semua" permission lifts the scope entirely', function () {
    scopeFixture();
    $user = scopedHr([User::SCOPE_BYPASS_EMPLOYEES]);

    $this->actingAs($user)->get('/employees')
        ->assertOk()
        ->assertSee('Sby Accounting')
        ->assertSee('Jkt Accounting');
});

test('a user with neither a scope nor the bypass sees nobody', function () {
    scopeFixture();
    $user = scopedHr();

    $this->actingAs($user)->get('/employees')
        ->assertOk()
        ->assertSee('Cakupan akses Anda belum diatur')
        ->assertDontSee('Sby Accounting')
        ->assertDontSee('Jkt Accounting');

    // And they cannot add anybody either — the record would land outside their view.
    $this->actingAs($user)->get('/employees/create')->assertForbidden();
});

test('an admin can set a user data scope from access control', function () {
    $fixture = scopeFixture();

    $admin = scopedHr([User::SCOPE_BYPASS_EMPLOYEES, 'access-control.view', 'access-control.update']);
    $target = scopedHr();

    $this->actingAs($admin)
        ->put("/access-control/users/{$target->id}/scope", [
            'branches' => [$fixture['surabaya']->id],
            'departments' => [$fixture['accounting']->id],
        ])
        ->assertRedirect('/access-control');

    expect($target->refresh()->accessBranchIds())->toBe([$fixture['surabaya']->id])
        ->and($target->accessDepartmentIds())->toBe([$fixture['accounting']->id]);

    $this->actingAs($target)->get('/employees')
        ->assertOk()
        ->assertSee('Sby Accounting')
        ->assertDontSee('Jkt Accounting');
});
