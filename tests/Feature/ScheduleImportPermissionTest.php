<?php

use App\Models\User;
use App\Support\MenuPermissions;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Impor roster punya izinnya sendiri.
 *
 * Dulu ia menumpang pada schedules.update, sehingga tidak pernah muncul sebagai kolom
 * di matriks Kontrol Akses: admin tidak punya cara memberikannya, dan yang belum
 * memegang "Ubah" hanya menemui 403 tanpa penjelasan.
 */
function scheduleUser(array $permissions): User
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
    expect(MenuPermissions::all())->toContain('schedules.import');

    // Dan benar-benar terlihat sebagai kolom pada baris Jadwal Kerja.
    expect(config('rbac.menus.Jadwal.schedules.actions'))->toContain('import');
});

test('all three import routes require it', function () {
    $withoutImport = scheduleUser(['schedules.view', 'schedules.update']);

    $this->actingAs($withoutImport)->get(route('attendance.schedules.import.template'))->assertForbidden();
    $this->actingAs($withoutImport)->post(route('attendance.schedules.import'))->assertForbidden();
    $this->actingAs($withoutImport)->get(route('attendance.schedules.import.errors', ['token' => 'x']))->assertForbidden();

    $withImport = scheduleUser(['schedules.view', 'schedules.import']);

    // Template terunduh tanpa perlu izin "Ubah" sama sekali.
    $this->actingAs($withImport)->get(route('attendance.schedules.import.template'))->assertOk();
});

test('the Import Excel button follows the same permission, and Generate does not', function () {
    $importer = scheduleUser(['schedules.view', 'schedules.import']);

    $this->actingAs($importer)->get(route('attendance.schedules.index'))
        ->assertOk()
        ->assertSee('Import Excel')
        ->assertDontSee('Generate Ulang');

    $updater = scheduleUser(['schedules.view', 'schedules.update']);

    $this->actingAs($updater)->get(route('attendance.schedules.index'))
        ->assertOk()
        ->assertSee('Generate Ulang')
        ->assertDontSee('Import Excel');
});

test('the seeder keeps giving it to hr-manager', function () {
    $this->seed(RbacSeeder::class);

    $role = Role::findByName('hr-manager', 'web');

    expect($role->hasPermissionTo('schedules.import'))->toBeTrue();
});
