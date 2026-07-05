<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\JobPosition;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function masterDataAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'dashboard.view',
        'organization.view',
        'organization.create',
        'organization.update',
        'organization.delete',
        'attendance.view',
        'attendance.create',
        'attendance.update',
        'attendance.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('admin can manage branch master data', function () {
    $admin = masterDataAdmin();

    $this->actingAs($admin)
        ->post(route('organization.branches.store'), [
            'code' => 'DPS-OFC-01',
            'name' => 'Denpasar Office 1',
            'type' => 'office',
            'city' => 'Denpasar',
            'province' => 'Bali',
            'address' => 'Jl. Gatot Subroto',
            'is_active' => '1',
        ])
        ->assertRedirect(route('organization.branches.index'));

    $branch = Branch::query()->where('code', 'DPS-OFC-01')->first();

    $this->actingAs($admin)
        ->put(route('organization.branches.update', $branch), [
            'code' => 'DPS-WHS-01',
            'name' => 'Denpasar Gudang 1',
            'type' => 'warehouse',
            'city' => 'Denpasar',
            'province' => 'Bali',
            'address' => 'Area Gudang Denpasar',
            'is_active' => '1',
        ])
        ->assertRedirect(route('organization.branches.index'));

    $this->assertDatabaseHas('branches', ['code' => 'DPS-WHS-01', 'name' => 'Denpasar Gudang 1', 'type' => 'warehouse']);
});

test('admin can manage department and job position master data', function () {
    $admin = masterDataAdmin();

    $this->actingAs($admin)
        ->post(route('organization.departments.store'), [
            'code' => 'MKT',
            'name' => 'Marketing',
            'description' => 'Sales and promotion.',
            'is_active' => '1',
        ])
        ->assertRedirect(route('organization.departments.index'));

    $department = Department::query()->where('code', 'MKT')->first();

    $this->actingAs($admin)
        ->post(route('organization.job-positions.store'), [
            'departments' => [$department->id],
            'code' => 'STF',
            'name' => 'Staff',
            'level' => 'Staff',
            'default_role_id' => null,
            'is_active' => '1',
        ])
        ->assertRedirect(route('organization.job-positions.index'));

    $jobPosition = JobPosition::query()->where('code', 'STF')->first();

    $this->actingAs($admin)
        ->put(route('organization.job-positions.update', $jobPosition), [
            'departments' => [$department->id],
            'code' => 'SPV',
            'name' => 'Supervisor',
            'level' => 'Supervisor',
            'default_role_id' => null,
            'is_active' => '1',
        ])
        ->assertRedirect(route('organization.job-positions.index'));

    $this->assertDatabaseHas('job_positions', ['code' => 'SPV', 'name' => 'Supervisor']);
    $this->assertDatabaseHas('department_job_position', ['department_id' => $department->id, 'job_position_id' => $jobPosition->id, 'is_active' => true]);
});

test('admin can manage shift master data', function () {
    $admin = masterDataAdmin();

    $this->actingAs($admin)
        ->post(route('attendance.shifts.store'), [
            'code' => 'MLM',
            'name' => 'Shift Malam',
            'start_time' => '23:00',
            'end_time' => '07:00',
            'break_minutes' => 45,
            'is_active' => '1',
        ])
        ->assertRedirect(route('attendance.shifts.index'));

    $shift = Shift::query()->where('code', 'MLM')->first();

    $this->actingAs($admin)
        ->put(route('attendance.shifts.update', $shift), [
            'code' => 'MLM',
            'name' => 'Shift Malam Gudang',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_minutes' => 60,
            'is_active' => '1',
        ])
        ->assertRedirect(route('attendance.shifts.index'));

    $this->assertDatabaseHas('shifts', ['code' => 'MLM', 'name' => 'Shift Malam Gudang']);

    $this->actingAs($admin)->delete(route('attendance.shifts.destroy', $shift))->assertRedirect(route('attendance.shifts.index'));

    $this->assertDatabaseMissing('shifts', ['code' => 'MLM']);
});

test('master data pages are protected by permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('organization.branches.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('attendance.shifts.index'))
        ->assertForbidden();
});
