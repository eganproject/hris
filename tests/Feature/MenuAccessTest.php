<?php

use App\Models\User;
use App\Support\MenuPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * A user holding exactly the given permissions — nothing else.
 *
 * @param  list<string>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Register the whole catalog so a permission the user does NOT hold still exists
    // (Spatie throws on unknown names instead of answering "no").
    foreach (MenuPermissions::all() as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('access to one menu does not open the other menus', function () {
    // Dulu satu "attendance.view" membuka 12 menu sekaligus; sekarang tidak lagi.
    $user = userWithPermissions(['dashboard.view', 'leave.view', 'leave.update']);

    $this->actingAs($user)->get('/attendance/leave')->assertOk();

    foreach ([
        '/attendance/daily',
        '/attendance/devices',
        '/attendance/shifts',
        '/attendance/holidays',
        '/attendance/schedules',
        '/attendance/schedule-patterns',
        '/attendance/corrections',
        '/attendance/overtime',
        '/attendance/swaps',
        '/attendance/punches',
        '/attendance/leave-types',
        '/attendance/leave-balances',
        '/reports/attendance',
        '/employees',
        '/settings',
    ] as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
});

test('the sidebar only lists the menus a role may open', function () {
    $user = userWithPermissions(['dashboard.view', 'leave.view']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Cuti & Izin', escape: false)
        ->assertDontSee('Perangkat Absensi')
        ->assertDontSee('Shift Kerja')
        ->assertDontSee('Data Karyawan');
});

test('read access does not grant write actions on the same menu', function () {
    $user = userWithPermissions(['dashboard.view', 'shifts.view']);

    $this->actingAs($user)->get('/attendance/shifts')->assertOk();
    $this->actingAs($user)->get('/attendance/shifts/create')->assertForbidden();
    $this->actingAs($user)->post('/attendance/shifts', [])->assertForbidden();
});

test('export and import are separate from view and create', function () {
    $viewer = userWithPermissions(['dashboard.view', 'employees.view']);

    $this->actingAs($viewer)->get('/employees')->assertOk();
    $this->actingAs($viewer)->get('/employees/export')->assertForbidden();
    $this->actingAs($viewer)->get('/employees/import/template')->assertForbidden();

    $exporter = userWithPermissions(['dashboard.view', 'employees.view', 'employees.export']);

    $this->actingAs($exporter)->get('/employees/export')->assertOk();
    $this->actingAs($exporter)->get('/employees/import/template')->assertForbidden();
});

test('each report has its own permission', function () {
    $user = userWithPermissions(['dashboard.view', 'reports.leave.view']);

    $this->actingAs($user)->get('/reports')->assertOk();
    $this->actingAs($user)->get('/reports/leave')->assertOk();
    $this->actingAs($user)->get('/reports/leave/export')->assertForbidden();
    $this->actingAs($user)->get('/reports/attendance')->assertForbidden();
    $this->actingAs($user)->get('/reports/attendance-log')->assertForbidden();
});

test('an admin can grant a menu action to a role from the matrix', function () {
    $admin = userWithPermissions(['dashboard.view', 'access-control.view', 'access-control.update']);
    $role = Role::findOrCreate('admin-cuti', 'web');

    $member = User::factory()->create();
    $member->assignRole($role);

    $this->actingAs($member)->get('/attendance/leave')->assertForbidden();

    $this->actingAs($admin)
        ->put(route('access-control.roles.update', $role), [
            'permissions' => ['leave.view', 'leave.update'],
        ])
        ->assertRedirect(route('access-control.index'));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['leave.update', 'leave.view']);

    $this->actingAs($member)->get('/attendance/leave')->assertOk();
    $this->actingAs($member)->get('/attendance/shifts')->assertForbidden();
});

test('the superadmin role cannot have its access stripped', function () {
    $admin = userWithPermissions(['dashboard.view', 'access-control.view', 'access-control.update']);
    $superadmin = Role::findOrCreate('superadmin', 'web');
    $superadmin->givePermissionTo(MenuPermissions::all());

    $this->actingAs($admin)
        ->put(route('access-control.roles.update', $superadmin), ['permissions' => ['leave.view']])
        ->assertForbidden();

    expect($superadmin->fresh()->permissions)->toHaveCount(count(MenuPermissions::all()));
});
