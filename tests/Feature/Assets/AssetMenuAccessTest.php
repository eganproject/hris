<?php

use App\Models\User;
use App\Support\MenuPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pengguna yang memegang persis izin yang disebut — tidak lebih.
 *
 * @param  list<string>  $permissions
 */
function assetMenuUser(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Seluruh katalog didaftarkan agar izin yang TIDAK dipegang tetap ada
    // (Spatie melempar galat untuk nama yang tak dikenal, bukan menjawab "tidak").
    foreach (MenuPermissions::all() as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('izin aset masuk ke katalog kontrol akses', function () {
    expect(MenuPermissions::all())
        ->toContain('assets.view', 'assets.create', 'assets.update', 'assets.delete', 'assets.export')
        ->toContain('asset-categories.view', 'asset-categories.create', 'asset-categories.update', 'asset-categories.delete')
        ->toContain('assets.view.all');
});

test('melihat aset tidak otomatis boleh menambah, mengekspor, atau mengurus kategori', function () {
    $user = assetMenuUser(['dashboard.view', 'assets.view', 'assets.view.all']);

    $this->actingAs($user)->get('/assets')->assertOk();
    $this->actingAs($user)->get('/assets/create')->assertForbidden();
    $this->actingAs($user)->post('/assets', [])->assertForbidden();
    $this->actingAs($user)->get('/assets/export')->assertForbidden();
    $this->actingAs($user)->get('/assets/categories')->assertForbidden();
});

test('izin kategori aset berdiri sendiri dari izin aset', function () {
    $user = assetMenuUser(['dashboard.view', 'asset-categories.view']);

    $this->actingAs($user)->get('/assets/categories')->assertOk();
    $this->actingAs($user)->get('/assets/categories/create')->assertForbidden();
    $this->actingAs($user)->get('/assets')->assertForbidden();
});

test('menu aset tidak muncul di sidebar tanpa izinnya', function () {
    $withAssets = assetMenuUser(['dashboard.view', 'assets.view', 'assets.view.all']);
    $withoutAssets = assetMenuUser(['dashboard.view']);

    $this->actingAs($withAssets)->get('/dashboard')->assertOk()->assertSee('Daftar Aset');
    $this->actingAs($withoutAssets)->get('/dashboard')->assertOk()->assertDontSee('Daftar Aset');
});

test('modul lain tidak ikut terbuka oleh izin aset', function () {
    $user = assetMenuUser(['dashboard.view', 'assets.view', 'assets.view.all']);

    foreach (['/employees', '/attendance/daily', '/attendance/leave', '/reports/attendance', '/settings'] as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
});
