<?php

use App\Enums\AssetStatus;
use App\Exports\AssetsExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pengguna dengan izin melihat aset TAPI tanpa "lihat semua lokasi & divisi":
 * cakupannyalah yang menentukan apa yang ia lihat.
 */
function scopedAssetOfficer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['assets.view', 'assets.create', 'assets.update', 'assets.delete', 'assets.export'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Permission::findOrCreate(User::SCOPE_BYPASS_ASSETS, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array<string, mixed> */
function assetScopeFixture(): array
{
    $category = AssetCategory::query()->create([
        'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true,
    ]);

    $ho = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    $it = Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);

    $make = function (string $name, Branch $owning, Branch $current, Department $department) use ($category): Asset {
        $asset = Asset::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'owning_branch_id' => $owning->id,
            'current_branch_id' => $current->id,
            'department_id' => $department->id,
            'status' => AssetStatus::Available->value,
            'condition' => 'good',
        ]);

        return $asset->refresh();
    };

    return [
        'ho' => $ho,
        'sby' => $sby,
        'it' => $it,
        'ops' => $ops,
        'hoAsset' => $make('Laptop HO', $ho, $ho, $it),
        'sbyAsset' => $make('Laptop Surabaya', $sby, $sby, $ops),
        // Milik HO tapi sedang berada di Surabaya: harus tetap terlihat oleh HO.
        'lentAsset' => $make('Laptop HO dititipkan', $ho, $sby, $it),
    ];
}

test('pengguna hanya melihat aset di lokasi kerjanya', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id]);

    $this->actingAs($user)->get(route('assets.index'))
        ->assertOk()
        ->assertSee('Laptop HO')
        ->assertDontSee('Laptop Surabaya');
});

test('aset yang dititipkan ke cabang lain tetap terlihat oleh cabang pemiliknya', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id]);

    $this->actingAs($user)->get(route('assets.show', $fixture['lentAsset']))->assertOk();
});

test('membuka aset di luar cakupan ditolak', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id]);

    $this->actingAs($user)->get(route('assets.show', $fixture['sbyAsset']))->assertForbidden();
    $this->actingAs($user)->get(route('assets.edit', $fixture['sbyAsset']))->assertForbidden();
    $this->actingAs($user)->delete(route('assets.destroy', $fixture['sbyAsset']))->assertForbidden();
});

test('cakupan divisi ikut mempersempit daftar aset', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id, $fixture['sby']->id]);
    $user->accessDepartments()->sync([$fixture['ops']->id]);

    $this->actingAs($user)->get(route('assets.index'))
        ->assertOk()
        ->assertSee('Laptop Surabaya')
        ->assertDontSee('Laptop HO');
});

test('aset milik dua divisi terlihat oleh kedua divisi', function () {
    $fixture = assetScopeFixture();
    $fixture['hoAsset']->departments()->syncWithoutDetaching([$fixture['ops']->id]);

    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id]);
    $user->accessDepartments()->sync([$fixture['ops']->id]);

    $this->actingAs($user)->get(route('assets.show', $fixture['hoAsset']))->assertOk();
});

test('pengguna tanpa cakupan sama sekali tidak melihat aset apa pun', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();

    $this->actingAs($user)->get(route('assets.index'))
        ->assertOk()
        ->assertDontSee('Laptop HO')
        ->assertSee('Cakupan akses Anda belum diatur', escape: false);

    $this->actingAs($user)->get(route('assets.show', $fixture['hoAsset']))->assertForbidden();
});

test('akun yang dibatasi ke bawahan belum melihat aset dan diberi penjelasan', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id]);
    $user->forceFill(['limit_to_subordinates' => true])->save();

    $this->actingAs($user)->get(route('assets.index'))
        ->assertOk()
        ->assertDontSee('Laptop HO')
        ->assertSee('Akun Anda dibatasi ke bawahan', escape: false);
});

test('ekspor mengikuti cakupan yang sama dengan daftarnya', function () {
    $fixture = assetScopeFixture();
    $user = scopedAssetOfficer();
    $user->accessBranches()->sync([$fixture['ho']->id]);
    Permission::findOrCreate('assets.export', 'web');
    $user->givePermissionTo('assets.export');

    $rows = (new AssetsExport([], $user))->query()->pluck('name')->all();

    expect($rows)->toContain('Laptop HO')
        ->and($rows)->not->toContain('Laptop Surabaya');
});
