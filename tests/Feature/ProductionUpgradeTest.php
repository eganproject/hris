<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Simulates the production database as it is TODAY (coarse permissions), then runs
 * the per-menu migration on it and checks that nobody lost access.
 */
test('the production upgrade keeps every role and user working', function () {
    // 1. Roll the DB back to the state before the split, and recreate the old data.
    $migration = require database_path('migrations/2026_07_14_120000_split_permissions_per_menu.php');

    DB::table('role_has_permissions')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('permissions')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $old = [
        'dashboard.view',
        'employees.view', 'employees.create', 'employees.update', 'employees.delete', 'employees.view.all',
        'attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete', 'attendance.view.all',
        'organization.view', 'organization.create', 'organization.update', 'organization.delete',
        'access-control.view', 'access-control.update',
        'leave.request', 'attendance.correction', 'schedule.swap', 'overtime.request',
    ];

    foreach ($old as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $hr = Role::findOrCreate('hr-manager', 'web');
    $hr->syncPermissions(array_diff($old, ['access-control.view', 'access-control.update']));

    $karyawan = Role::findOrCreate('employee', 'web');
    $karyawan->syncPermissions(['dashboard.view', 'leave.request', 'attendance.correction', 'schedule.swap', 'overtime.request']);

    $hrUser = User::factory()->create();
    $hrUser->assignRole($hr);

    $staffUser = User::factory()->create();
    $staffUser->assignRole($karyawan);

    // Self-service butuh akun yang tertaut data karyawan (bukan soal permission).
    Employee::query()->create([
        'user_id' => $staffUser->id,
        'full_name' => 'Staf Biasa',
        'join_date' => now()->subYear()->toDateString(),
        'employment_status' => 'active',
    ]);

    // A user with a permission granted directly (not through a role).
    $directUser = User::factory()->create();
    $directUser->givePermissionTo(['dashboard.view', 'attendance.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 2. Run the migration exactly as production will.
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 3. Nobody may lose what they had.
    $hrUser->refresh();
    expect($hrUser->can('leave.view'))->toBeTrue()
        ->and($hrUser->can('leave.update'))->toBeTrue()
        ->and($hrUser->can('devices.delete'))->toBeTrue()
        ->and($hrUser->can('shifts.create'))->toBeTrue()
        ->and($hrUser->can('schedules.update'))->toBeTrue()
        ->and($hrUser->can('reports.attendance.view'))->toBeTrue()
        ->and($hrUser->can('reports.leave.export'))->toBeTrue()
        ->and($hrUser->can('branches.delete'))->toBeTrue()
        ->and($hrUser->can('settings.update'))->toBeTrue()
        ->and($hrUser->can('employees.export'))->toBeTrue()
        ->and($hrUser->can('employees.import'))->toBeTrue()
        ->and($hrUser->can('employees.view.all'))->toBeTrue()
        ->and($hrUser->can('attendance.view.all'))->toBeTrue()
        // ...and must not silently gain what it never had.
        ->and($hrUser->can('access-control.update'))->toBeFalse();

    $staffUser->refresh();
    expect($staffUser->can('my-leave.view'))->toBeTrue()
        ->and($staffUser->can('my-attendance.view'))->toBeTrue()
        ->and($staffUser->can('my-schedule.view'))->toBeTrue()
        ->and($staffUser->can('my-overtime.view'))->toBeTrue()
        ->and($staffUser->can('leave.view'))->toBeFalse();

    // The directly-granted permission survives the split too.
    $directUser->refresh();
    expect($directUser->can('leave.view'))->toBeTrue()
        ->and($directUser->can('shifts.view'))->toBeTrue()
        ->and($directUser->can('leave.update'))->toBeFalse();

    // 4. The old names are gone, so nothing can still be checked against them.
    // ("organization.view" stays: it is now the Struktur Organisasi menu itself.)
    expect(Permission::query()->whereIn('name', [
        'attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete',
        'organization.create', 'organization.update', 'organization.delete',
        'leave.request', 'attendance.correction', 'schedule.swap', 'overtime.request',
    ])->count())->toBe(0)
        ->and(Permission::query()->where('name', 'organization.view')->exists())->toBeTrue();

    // 5. The real pages answer accordingly.
    $this->actingAs($hrUser)->get('/attendance/leave')->assertOk();
    $this->actingAs($hrUser)->get('/attendance/shifts')->assertOk();
    $this->actingAs($hrUser)->get('/employees')->assertOk();
    $this->actingAs($hrUser)->get('/access-control')->assertForbidden();
    $this->actingAs($staffUser)->get('/my-leave')->assertOk();
    $this->actingAs($staffUser)->get('/attendance/leave')->assertForbidden();
});
