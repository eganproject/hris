<?php

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
 * Peta Absen Mandiri punya izinnya sendiri.
 *
 * Dulu ia menumpang pada attendance-daily.view, sehingga tidak pernah muncul sebagai
 * baris di matriks Kontrol Akses: akses ke titik koordinat absen mandiri tidak bisa
 * dipisahkan dari akses ke papan absensi harian.
 */
function mapPermissionUser(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

test('the permission appears in the access-control catalogue', function () {
    expect(MenuPermissions::all())->toContain('attendance-map.view');

    // Dan benar-benar terlihat sebagai baris pada grup Absensi.
    expect(config('rbac.menus.Absensi.attendance-map.label'))->toBe('Peta Absen Mandiri');
});

test('the map route requires it, and the daily board no longer implies it', function () {
    $dailyOnly = mapPermissionUser(['attendance-daily.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($dailyOnly)->get(route('attendance.map'))->assertForbidden();
    $this->actingAs($dailyOnly)->get(route('attendance.daily.index'))->assertOk();

    $mapOnly = mapPermissionUser(['attendance-map.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($mapOnly)->get(route('attendance.map'))->assertOk();
    $this->actingAs($mapOnly)->get(route('attendance.daily.index'))->assertForbidden();
});

test('the sidebar link follows the same permission', function () {
    $dailyOnly = mapPermissionUser(['attendance-daily.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($dailyOnly)->get(route('attendance.daily.index'))
        ->assertOk()
        ->assertDontSee('Peta Absen Mandiri');

    // Grup "Absensi" tetap terbuka bagi yang hanya memegang izin peta.
    $mapOnly = mapPermissionUser(['attendance-map.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($mapOnly)->get(route('attendance.map'))
        ->assertOk()
        ->assertSee('Peta Absen Mandiri');
});

test('the seeder keeps giving it to hr-manager', function () {
    $this->seed(RbacSeeder::class);

    expect(Role::findByName('hr-manager', 'web')->hasPermissionTo('attendance-map.view'))->toBeTrue();
});

/**
 * Produksi: hak akses sudah diatur manual lewat Kontrol Akses, jadi migration hanya
 * boleh MENAMBAH — tidak boleh menimpa atau menghapus pemberian hak yang ada.
 */
test('the migration grants it to current holders without touching anything else', function () {
    $migration = require database_path('migrations/2026_08_30_100000_add_attendance_map_permission.php');

    DB::table('role_has_permissions')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('permissions')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['attendance-daily.view', 'attendance-daily.update', 'employees.view', 'dashboard.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    // Role yang sudah "diotak-atik": bukan bawaan config/rbac.php.
    $supervisor = Role::findOrCreate('supervisor-lapangan', 'web');
    $supervisor->syncPermissions(['attendance-daily.view', 'employees.view']);

    $tanpaAbsensi = Role::findOrCreate('staf-rekrutmen', 'web');
    $tanpaAbsensi->syncPermissions(['employees.view', 'dashboard.view']);

    // Pengguna dengan izin yang ditempel langsung, bukan lewat role.
    $langsung = User::factory()->create();
    $langsung->givePermissionTo(['attendance-daily.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Yang memegang papan harian ikut dapat peta.
    expect($supervisor->fresh()->hasPermissionTo('attendance-map.view'))->toBeTrue()
        ->and($langsung->fresh()->can('attendance-map.view'))->toBeTrue();

    // Yang tidak memegangnya tetap tidak dapat apa-apa yang baru.
    expect($tanpaAbsensi->fresh()->hasPermissionTo('attendance-map.view'))->toBeFalse();

    // Dan tidak ada hak lama yang hilang atau bertambah diam-diam.
    expect($supervisor->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['attendance-daily.view', 'attendance-map.view', 'employees.view'])
        ->and($tanpaAbsensi->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['dashboard.view', 'employees.view']);
});

test('running the migration twice changes nothing', function () {
    $migration = require database_path('migrations/2026_08_30_100000_add_attendance_map_permission.php');

    Permission::findOrCreate('attendance-daily.view', 'web');
    $role = Role::findOrCreate('supervisor-lapangan', 'web');
    $role->syncPermissions(['attendance-daily.view']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'attendance-map.view')->count())->toBe(1)
        ->and($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['attendance-daily.view', 'attendance-map.view']);
});
