<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantPermissions(User $user, array $permissions): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);
}

test('payroll menu is hidden and route is forbidden without payroll permission', function () {
    $user = User::factory()->create();

    grantPermissions($user, [
        'dashboard.view',
        'employees.view',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('Data Karyawan')
        ->assertDontSee('Gaji / Payroll');

    $this->actingAs($user)
        ->get('/payroll')
        ->assertForbidden();
});

test('employee read only users can see employee menu without mutation buttons', function () {
    $user = User::factory()->create();
    Employee::query()->create();

    grantPermissions($user, [
        'dashboard.view',
        'employees.view',
    ]);

    $this->actingAs($user)
        ->get('/employees')
        ->assertSuccessful()
        ->assertSee('Manajemen Karyawan')
        ->assertDontSee('Tambah Karyawan')
        ->assertDontSee('Edit')
        ->assertDontSee('Hapus');

    $this->actingAs($user)
        ->get('/employees/create')
        ->assertForbidden();
});

test('employee managers can see and access employee mutation actions', function () {
    $user = User::factory()->create();
    $employee = Employee::query()->create();

    grantPermissions($user, [
        'dashboard.view',
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
    ]);

    $this->actingAs($user)
        ->get('/employees')
        ->assertSuccessful()
        ->assertSee('Tambah Karyawan')
        ->assertSee('Edit')
        ->assertSee('Hapus');

    $this->actingAs($user)
        ->get('/employees/create')
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('employees.edit', $employee))
        ->assertSuccessful();
});
