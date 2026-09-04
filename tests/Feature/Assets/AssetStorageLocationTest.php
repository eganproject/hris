<?php

use App\Models\AssetStorageLocation;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function storageAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'asset-storage-locations.view',
        'asset-storage-locations.create',
        'asset-storage-locations.update',
        'asset-storage-locations.delete',
        'assets.view.all',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array<string, mixed> */
function storageTree(): array
{
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);

    $floor = AssetStorageLocation::query()->create(['branch_id' => $branch->id, 'name' => 'Lantai 4']);
    $warehouse = AssetStorageLocation::query()->create(['branch_id' => $branch->id, 'parent_id' => $floor->id, 'name' => 'Gudang A']);
    $rack = AssetStorageLocation::query()->create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak B']);

    return compact('branch', 'floor', 'warehouse', 'rack');
}

test('jalur lengkap tersusun dari induk ke anak', function () {
    $tree = storageTree();

    expect($tree['floor']->full_path)->toBe('Lantai 4')
        ->and($tree['warehouse']->full_path)->toBe('Lantai 4 › Gudang A')
        ->and($tree['rack']->full_path)->toBe('Lantai 4 › Gudang A › Rak B')
        ->and($tree['rack']->depth)->toBe(2);
});

test('jenjang dua tingkat juga sah', function () {
    $tree = storageTree();

    $room = AssetStorageLocation::query()->create([
        'branch_id' => $tree['branch']->id, 'parent_id' => $tree['floor']->id, 'name' => 'Ruang Office A',
    ]);

    expect($room->full_path)->toBe('Lantai 4 › Ruang Office A')
        ->and($room->depth)->toBe(1);
});

test('mengganti nama induk ikut memperbarui jalur seluruh keturunannya', function () {
    $tree = storageTree();

    $this->actingAs(storageAdmin())
        ->put(route('assets.storage-locations.update', $tree['warehouse']), [
            'name' => 'Gudang Utama',
            'parent_id' => $tree['floor']->id,
            'is_active' => '1',
        ])
        ->assertRedirect(route('assets.storage-locations.index'));

    expect($tree['warehouse']->fresh()->full_path)->toBe('Lantai 4 › Gudang Utama')
        ->and($tree['rack']->fresh()->full_path)->toBe('Lantai 4 › Gudang Utama › Rak B');
});

test('memindahkan satu cabang pohon ikut memperbarui jalur di bawahnya', function () {
    $tree = storageTree();

    $otherFloor = AssetStorageLocation::query()->create([
        'branch_id' => $tree['branch']->id, 'name' => 'Lantai 2',
    ]);

    $this->actingAs(storageAdmin())
        ->put(route('assets.storage-locations.update', $tree['warehouse']), [
            'name' => 'Gudang A',
            'parent_id' => $otherFloor->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($tree['warehouse']->fresh()->full_path)->toBe('Lantai 2 › Gudang A')
        ->and($tree['rack']->fresh()->full_path)->toBe('Lantai 2 › Gudang A › Rak B')
        ->and($tree['rack']->fresh()->depth)->toBe(2);
});

test('induk tidak boleh dirinya sendiri atau keturunannya', function () {
    $tree = storageTree();
    $admin = storageAdmin();

    $this->actingAs($admin)
        ->put(route('assets.storage-locations.update', $tree['warehouse']), [
            'name' => 'Gudang A', 'parent_id' => $tree['warehouse']->id,
        ])
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($admin)
        ->put(route('assets.storage-locations.update', $tree['warehouse']), [
            'name' => 'Gudang A', 'parent_id' => $tree['rack']->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('susunan tidak boleh melebihi batas jenjang', function () {
    $tree = storageTree();

    // Lantai 4 > Gudang A > Rak B sudah tiga jenjang; jenjang keempat masih boleh.
    $box = AssetStorageLocation::query()->create([
        'branch_id' => $tree['branch']->id, 'parent_id' => $tree['rack']->id, 'name' => 'Kotak 1',
    ]);

    expect($box->depth)->toBe(AssetStorageLocation::MAX_DEPTH - 1);

    $this->actingAs(storageAdmin())
        ->post(route('assets.storage-locations.store'), [
            'branch_id' => $tree['branch']->id,
            'parent_id' => $box->id,
            'name' => 'Sekat A',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('memindahkan cabang yang dalam ditolak bila keturunannya jadi tidak muat', function () {
    $tree = storageTree();

    AssetStorageLocation::query()->create([
        'branch_id' => $tree['branch']->id, 'parent_id' => $tree['rack']->id, 'name' => 'Kotak 1',
    ]);

    // Gudang A membawa dua jenjang di bawahnya (Rak B > Kotak 1). Menaruhnya di
    // bawah Rak B milik lantai lain akan membuat isinya melewati batas.
    $deepFloor = AssetStorageLocation::query()->create(['branch_id' => $tree['branch']->id, 'name' => 'Lantai 2']);
    $deepRoom = AssetStorageLocation::query()->create([
        'branch_id' => $tree['branch']->id, 'parent_id' => $deepFloor->id, 'name' => 'Ruang B',
    ]);

    $this->actingAs(storageAdmin())
        ->put(route('assets.storage-locations.update', $tree['warehouse']), [
            'name' => 'Gudang A', 'parent_id' => $deepRoom->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('induk harus berada di lokasi kerja yang sama', function () {
    $tree = storageTree();
    $other = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    $this->actingAs(storageAdmin())
        ->post(route('assets.storage-locations.store'), [
            'branch_id' => $other->id,
            'parent_id' => $tree['floor']->id,
            'name' => 'Gudang Surabaya',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('nama harus unik di antara saudara, tapi boleh berulang di induk lain', function () {
    $tree = storageTree();
    $admin = storageAdmin();

    $this->actingAs($admin)
        ->post(route('assets.storage-locations.store'), [
            'branch_id' => $tree['branch']->id,
            'parent_id' => $tree['warehouse']->id,
            'name' => 'Rak B',
        ])
        ->assertSessionHasErrors('name');

    // "Rak B" di gudang lain adalah tempat yang berbeda, jadi tidak bentrok.
    $otherWarehouse = AssetStorageLocation::query()->create([
        'branch_id' => $tree['branch']->id, 'parent_id' => $tree['floor']->id, 'name' => 'Gudang B',
    ]);

    $this->actingAs($admin)
        ->post(route('assets.storage-locations.store'), [
            'branch_id' => $tree['branch']->id,
            'parent_id' => $otherWarehouse->id,
            'name' => 'Rak B',
        ])
        ->assertRedirect(route('assets.storage-locations.index'));
});

test('tempat yang masih memuat rak lain tidak bisa dihapus', function () {
    $tree = storageTree();

    $this->actingAs(storageAdmin())
        ->delete(route('assets.storage-locations.destroy', $tree['warehouse']))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AssetStorageLocation::query()->whereKey($tree['warehouse']->id)->exists())->toBeTrue();
});

test('lokasi kerja terkunci setelah tempat penyimpanan dibuat', function () {
    $tree = storageTree();
    $other = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    $this->actingAs(storageAdmin())
        ->put(route('assets.storage-locations.update', $tree['floor']), [
            'branch_id' => $other->id,
            'name' => 'Lantai 4',
        ])
        ->assertRedirect();

    expect($tree['floor']->fresh()->branch_id)->toBe($tree['branch']->id);
});

test('halaman master tempat penyimpanan bisa dibuka', function () {
    $tree = storageTree();
    $admin = storageAdmin();

    $this->actingAs($admin)->get(route('assets.storage-locations.index'))
        ->assertOk()
        ->assertSee('Lantai 4 › Gudang A › Rak B', escape: false);

    $this->actingAs($admin)->get(route('assets.storage-locations.create'))->assertOk();
    $this->actingAs($admin)->get(route('assets.storage-locations.edit', $tree['rack']))->assertOk();
});
