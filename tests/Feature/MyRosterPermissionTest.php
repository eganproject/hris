<?php

use App\Models\Employee;
use App\Models\User;
use App\Support\MenuPermissions;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Jadwal Saya punya izinnya sendiri.
 *
 * Dulu /jadwal-saya tidak dijaga izin apa pun, sehingga tidak muncul di matriks
 * Kontrol Akses dan tidak bisa disembunyikan dari role mana pun.
 */
function rosterPermissionEmployee(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Rina', 'employment_status' => 'active',
    ]);

    return $user;
}

test('the permission appears in the access-control catalogue', function () {
    expect(MenuPermissions::all())->toContain('my-roster.view')
        ->and(config('rbac.menus.Self-service.my-roster.label'))->toBe('Jadwal Saya');
});

test('the page requires it', function () {
    $without = rosterPermissionEmployee(['dashboard.view', 'my-attendance.view']);
    $this->actingAs($without)->get(route('my-roster.index'))->assertForbidden();

    $with = rosterPermissionEmployee(['dashboard.view', 'my-roster.view']);
    $this->actingAs($with)->get(route('my-roster.index'))->assertOk();
});

test('the sidebar, bottom bar and dashboard link follow the same permission', function () {
    $without = rosterPermissionEmployee(['dashboard.view', 'my-attendance.view']);
    $this->actingAs($without)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('my-roster.index'), false);

    $with = rosterPermissionEmployee(['dashboard.view', 'my-attendance.view', 'my-roster.view']);
    $this->actingAs($with)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('my-roster.index'), false);
});

test('the seeder gives it to the default roles', function () {
    $this->seed(RbacSeeder::class);

    expect(Role::findByName('employee', 'web')->hasPermissionTo('my-roster.view'))->toBeTrue()
        ->and(Role::findByName('hr-manager', 'web')->hasPermissionTo('my-roster.view'))->toBeTrue();
});

/**
 * Produksi: hak akses sudah diatur manual lewat Kontrol Akses, jadi migration hanya
 * boleh MENAMBAH — tidak boleh menimpa atau menghapus pemberian hak yang ada.
 */
test('the migration grants it to current holders without touching anything else', function () {
    $migration = require database_path('migrations/2026_09_14_100000_add_my_roster_permission.php');

    DB::table('role_has_permissions')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('permissions')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['my-attendance.view', 'my-leave.view', 'employees.view', 'dashboard.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $karyawan = Role::findOrCreate('karyawan-cabang', 'web');
    $karyawan->syncPermissions(['my-attendance.view', 'my-leave.view']);

    $tanpaSelfService = Role::findOrCreate('staf-rekrutmen', 'web');
    $tanpaSelfService->syncPermissions(['employees.view', 'dashboard.view']);

    $langsung = User::factory()->create();
    $langsung->givePermissionTo(['my-attendance.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($karyawan->fresh()->hasPermissionTo('my-roster.view'))->toBeTrue()
        ->and($langsung->fresh()->can('my-roster.view'))->toBeTrue()
        ->and($tanpaSelfService->fresh()->hasPermissionTo('my-roster.view'))->toBeFalse();

    expect($karyawan->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['my-attendance.view', 'my-leave.view', 'my-roster.view'])
        ->and($tanpaSelfService->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['dashboard.view', 'employees.view']);
});

test('running the migration twice changes nothing', function () {
    $migration = require database_path('migrations/2026_09_14_100000_add_my_roster_permission.php');

    Permission::findOrCreate('my-attendance.view', 'web');
    $role = Role::findOrCreate('karyawan-cabang', 'web');
    $role->syncPermissions(['my-attendance.view']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'my-roster.view')->count())->toBe(1)
        ->and($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['my-attendance.view', 'my-roster.view']);
});
