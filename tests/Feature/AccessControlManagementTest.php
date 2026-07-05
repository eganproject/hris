<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function accessAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['access-control.view', 'access-control.update', 'dashboard.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['access-control.view', 'access-control.update', 'dashboard.view']);

    return $user;
}

function accessEmployeeFixture(): array
{
    $branch = Branch::query()->create(['code' => 'JKT-OFC-01', 'name' => 'Jakarta Office 1', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'OTO', 'name' => 'Otomotif', 'is_active' => true]);
    $role = Role::findOrCreate('employee', 'web');
    $position = JobPosition::query()->create([
        'default_role_id' => $role->id,
        'code' => 'STF',
        'name' => 'Staff',
        'is_active' => true,
    ]);
    $position->departments()->attach($department->id, ['is_active' => true]);
    $branch->departments()->attach($department->id, ['is_primary' => true, 'is_active' => true]);

    return compact('branch', 'department', 'role', 'position');
}

test('admin can update default role for a job position', function () {
    $admin = accessAdmin();
    ['position' => $position] = accessEmployeeFixture();
    $readerRole = Role::findOrCreate('employee-reader', 'web');

    $this->actingAs($admin)
        ->put(route('access-control.job-positions.update', $position), [
            'default_role_id' => $readerRole->id,
        ])
        ->assertRedirect(route('access-control.index'));

    expect($position->fresh()->default_role_id)->toBe($readerRole->id);
});

test('admin can update role permissions from access control page', function () {
    $admin = accessAdmin();
    $role = Role::findOrCreate('employee-reader', 'web');

    foreach (['dashboard.view', 'employees.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->actingAs($admin)
        ->put(route('access-control.roles.update', $role), [
            'permissions' => ['dashboard.view', 'employees.view'],
        ])
        ->assertRedirect(route('access-control.index'));

    expect($role->fresh()->hasPermissionTo('employees.view'))->toBeTrue();
});

test('admin can update departments available at a branch', function () {
    $admin = accessAdmin();
    ['branch' => $branch, 'department' => $department] = accessEmployeeFixture();
    $accounting = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('access-control.branches.departments.update', $branch), [
            'departments' => [$department->id, $accounting->id],
            'primary_department_id' => $accounting->id,
        ])
        ->assertRedirect(route('access-control.index'));

    $branch->refresh()->load('departments');

    expect($branch->departments)->toHaveCount(2)
        ->and($branch->departments->firstWhere('id', $accounting->id)->pivot->is_primary)->toBe(1);
});

test('primary branch department must be selected', function () {
    $admin = accessAdmin();
    ['branch' => $branch, 'department' => $department] = accessEmployeeFixture();
    $accounting = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);

    $this->actingAs($admin)
        ->from(route('access-control.index'))
        ->put(route('access-control.branches.departments.update', $branch), [
            'departments' => [$department->id],
            'primary_department_id' => $accounting->id,
        ])
        ->assertRedirect(route('access-control.index'))
        ->assertSessionHasErrors('primary_department_id');
});

test('access control page is protected by permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/access-control')
        ->assertForbidden();
});
