<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('the org chart shows the reporting tree built from manager_id', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['employees.view', 'employees.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['employees.view', 'employees.view.all']);

    $bos = Employee::query()->create(['full_name' => 'Bos Besar', 'employment_status' => 'active']);
    Employee::query()->create(['full_name' => 'Bawahan Satu', 'employment_status' => 'active', 'manager_id' => $bos->id]);
    Employee::query()->create(['full_name' => 'Bawahan Dua', 'employment_status' => 'active', 'manager_id' => $bos->id]);

    $this->actingAs($user)->get(route('organization.chart'))
        ->assertOk()
        ->assertSee('Bagan Organisasi')
        ->assertSee('Bos Besar')
        ->assertSee('Bawahan Satu')
        ->assertSee('Bawahan Dua')
        ->assertSee('2 bawahan');
});

test('an employee whose manager is out of scope becomes a root without leaking the manager', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('employees.view', 'web');

    $branchA = Branch::query()->create(['code' => 'A', 'name' => 'Cabang A', 'is_active' => true]);
    $branchB = Branch::query()->create(['code' => 'B', 'name' => 'Cabang B', 'is_active' => true]);

    // The manager sits in branch B; the user is scoped to branch A only.
    $user = User::factory()->create();
    $user->givePermissionTo('employees.view');
    $user->accessBranches()->attach($branchA->id);
    Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Atasan A', 'employment_status' => 'active', 'branch_id' => $branchA->id]);

    $bosB = Employee::query()->create(['full_name' => 'Bos Cabang B', 'employment_status' => 'active', 'branch_id' => $branchB->id]);
    Employee::query()->create(['full_name' => 'Anak Buah A', 'employment_status' => 'active', 'branch_id' => $branchA->id, 'manager_id' => $bosB->id]);

    $this->actingAs($user)->get(route('organization.chart'))
        ->assertOk()
        ->assertSee('Anak Buah A')
        ->assertDontSee('Bos Cabang B');
});

test('the org chart is closed to users without employees.view', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('dashboard.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('dashboard.view');

    $this->actingAs($user)->get(route('organization.chart'))->assertForbidden();
});
