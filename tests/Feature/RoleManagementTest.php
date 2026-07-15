<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// accessAdmin() sudah dideklarasikan di AccessControlManagementTest.php.

test('an admin can create a new role', function () {
    $admin = accessAdmin();

    $this->actingAs($admin)
        ->post(route('access-control.roles.store'), ['name' => 'supervisor-cabang'])
        ->assertRedirect(route('access-control.index'));

    expect(Role::query()->where('name', 'supervisor-cabang')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('a duplicate or invalid role name is rejected', function () {
    $admin = accessAdmin();
    Role::findOrCreate('sudah-ada', 'web');

    $this->actingAs($admin)
        ->from(route('access-control.index'))
        ->post(route('access-control.roles.store'), ['name' => 'sudah-ada'])
        ->assertRedirect(route('access-control.index'))
        ->assertSessionHasErrors('name');

    // Karakter aneh ditolak.
    $this->actingAs($admin)
        ->from(route('access-control.index'))
        ->post(route('access-control.roles.store'), ['name' => 'aneh/@!'])
        ->assertSessionHasErrors('name');
});

test('a role can be renamed', function () {
    $admin = accessAdmin();
    $role = Role::findOrCreate('lama', 'web');

    $this->actingAs($admin)
        ->patch(route('access-control.roles.rename', $role), ['name' => 'baru'])
        ->assertRedirect(route('access-control.index'));

    expect($role->fresh()->name)->toBe('baru');
});

test('an empty role can be deleted', function () {
    $admin = accessAdmin();
    $role = Role::findOrCreate('sekali-pakai', 'web');

    $this->actingAs($admin)
        ->delete(route('access-control.roles.destroy', $role))
        ->assertRedirect(route('access-control.index'));

    expect(Role::query()->where('name', 'sekali-pakai')->exists())->toBeFalse();
});

test('a role still assigned to users cannot be deleted', function () {
    $admin = accessAdmin();
    $role = Role::findOrCreate('dipakai', 'web');

    $member = User::factory()->create();
    $member->assignRole($role);

    $this->actingAs($admin)
        ->delete(route('access-control.roles.destroy', $role))
        ->assertRedirect(route('access-control.index'))
        ->assertSessionHas('error');

    expect(Role::query()->where('name', 'dipakai')->exists())->toBeTrue();
});

test('the superadmin role cannot be renamed or deleted', function () {
    $admin = accessAdmin();
    $superadmin = Role::findOrCreate('superadmin', 'web');

    $this->actingAs($admin)
        ->patch(route('access-control.roles.rename', $superadmin), ['name' => 'bukan-super'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('access-control.roles.destroy', $superadmin))
        ->assertForbidden();

    expect($superadmin->fresh()->name)->toBe('superadmin');
});

test('an admin can assign a role to an existing user and it takes effect', function () {
    $admin = accessAdmin();

    Permission::findOrCreate('leave.view', 'web');
    $role = Role::findOrCreate('supervisor-cuti', 'web');
    $role->syncPermissions(['leave.view']);

    $member = User::factory()->create();

    // Sebelumnya belum punya role & tidak bisa buka menu Cuti.
    expect($member->fresh()->hasRole('supervisor-cuti'))->toBeFalse();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $member), [
            'roles' => ['supervisor-cuti'],
            'branches' => [],
            'departments' => [],
        ])
        ->assertRedirect(route('access-control.index'));

    $member->refresh();

    expect($member->hasRole('supervisor-cuti'))->toBeTrue()
        ->and($member->can('leave.view'))->toBeTrue();

    // users_count pada daftar role kini menghitungnya.
    expect(Role::query()->withCount('users')->where('name', 'supervisor-cuti')->first()->users_count)->toBe(1);
});

test('unchecking every role removes the user from all roles', function () {
    $admin = accessAdmin();
    $role = Role::findOrCreate('sementara', 'web');

    $member = User::factory()->create();
    $member->assignRole($role);

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $member), ['roles' => [], 'branches' => [], 'departments' => []])
        ->assertRedirect(route('access-control.index'));

    expect($member->fresh()->roles)->toHaveCount(0);
});

test('managing roles requires the access-control update permission', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('access-control.view', 'web');
    Permission::findOrCreate('access-control.update', 'web');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('access-control.view');

    $this->actingAs($viewer)
        ->post(route('access-control.roles.store'), ['name' => 'nekat'])
        ->assertForbidden();

    expect(Role::query()->where('name', 'nekat')->exists())->toBeFalse();
});
