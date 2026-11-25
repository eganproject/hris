<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('superadmin and HR attendance holders get the Kabar menus', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('attendance-daily.update', 'web');
    Permission::findOrCreate('dashboard.view', 'web');

    $super = User::factory()->create();
    $super->assignRole(Role::findOrCreate('superadmin', 'web'));

    $hrRole = Role::findOrCreate('hr-staff', 'web');
    $hrRole->givePermissionTo('attendance-daily.update');
    $hr = User::factory()->create();
    $hr->assignRole($hrRole);

    $direct = User::factory()->create();
    $direct->givePermissionTo('attendance-daily.update');

    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('staff', 'web'));

    $migration = require database_path('migrations/2026_11_26_100300_grant_kabar_permissions_to_admins.php');
    $migration->up();
    $migration->up(); // aman dijalankan ulang

    foreach ([$super, $hr, $direct] as $user) {
        expect($user->fresh()->hasPermissionTo('announcements.create'))->toBeTrue()
            ->and($user->fresh()->hasPermissionTo('kabar-moderation.update'))->toBeTrue();
    }

    expect($staff->fresh()->hasPermissionTo('announcements.view'))->toBeFalse();

    $this->actingAs($super->fresh())->get(route('kabar.announcements.index'))->assertOk();
});
