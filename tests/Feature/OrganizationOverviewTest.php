<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function organizationViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('organization.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('organization.view');

    return $user;
}

test('organization page shows warehouse locations and departments', function () {
    $user = organizationViewer();

    $warehouse = Branch::query()->create([
        'code' => 'SBY-WHS-01',
        'name' => 'Surabaya Gudang 1',
        'type' => 'warehouse',
        'city' => 'Surabaya',
        'address' => 'Pergudangan Margomulyo',
        'is_active' => true,
    ]);
    $office = Branch::query()->create([
        'code' => 'JKT-OFC-01',
        'name' => 'Jakarta Office X',
        'type' => 'office',
        'city' => 'Jakarta',
        'is_active' => true,
    ]);
    $akrilik = Department::query()->create(['code' => 'AKR', 'name' => 'Akrilik & Aksesoris', 'is_active' => true]);
    $accounting = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);

    $warehouse->departments()->attach($akrilik->id, ['is_primary' => true, 'is_active' => true]);
    $office->departments()->attach([
        $akrilik->id => ['is_primary' => false, 'is_active' => true],
        $accounting->id => ['is_primary' => true, 'is_active' => true],
    ]);

    $position = JobPosition::query()->create([
        'code' => 'STF',
        'name' => 'Staff',
        'is_active' => true,
    ]);
    $position->departments()->attach($akrilik->id, ['is_active' => true]);

    Employee::query()->create([
        'branch_id' => $warehouse->id,
        'department_id' => $akrilik->id,
        'job_position_id' => $position->id,
        
        'full_name' => 'Bagus Pratama',
        'join_date' => now()->toDateString(),
        'employment_status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('organization.index'))
        ->assertSuccessful()
        ->assertSee('Surabaya Gudang 1')
        ->assertSee('Gudang')
        ->assertSee('Akrilik &amp; Aksesoris', false)
        ->assertSee('Jakarta Office X')
        ->assertSee('Accounting')
        ->assertSee('Karyawan Aktif');
});

test('organization page is protected by permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('organization.index'))
        ->assertForbidden();
});
