<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStorageLocation;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pengguna dengan izin aset penuh dan melihat semua lokasi/divisi.
 *
 * @param  list<string>  $permissions
 */
function assetAdmin(array $permissions = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = $permissions ?: [
        'assets.view', 'assets.create', 'assets.update', 'assets.delete', 'assets.export',
        'asset-categories.view', 'asset-categories.create', 'asset-categories.update', 'asset-categories.delete',
        'assets.view.all',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    // Selalu terdaftar agar can() bisa menjawab "tidak" alih-alih melempar galat.
    Permission::findOrCreate(User::SCOPE_BYPASS_ASSETS, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array<string, mixed> */
function assetFixture(): array
{
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $otherBranch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    // Lantai 4 › Gudang A › Rak B — tiga jenjang, seperti susunan sebenarnya.
    $floor = AssetStorageLocation::query()->create([
        'branch_id' => $branch->id, 'name' => 'Lantai 4', 'is_active' => true,
    ]);
    $warehouse = AssetStorageLocation::query()->create([
        'branch_id' => $branch->id, 'parent_id' => $floor->id, 'name' => 'Gudang A', 'is_active' => true,
    ]);
    $rack = AssetStorageLocation::query()->create([
        'branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak B', 'is_active' => true,
    ]);

    return [
        'category' => AssetCategory::query()->create([
            'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT',
            'requires_serial' => true, 'is_active' => true,
        ]),
        'branch' => $branch,
        'otherBranch' => $otherBranch,
        'floor' => $floor,
        'warehouse' => $warehouse,
        'rack' => $rack,
        'otherStorage' => AssetStorageLocation::query()->create([
            'branch_id' => $otherBranch->id, 'name' => 'Gudang Surabaya', 'is_active' => true,
        ]),
        'it' => Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]),
        'ops' => Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]),
    ];
}

/** @return array<string, mixed> */
function assetPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'category_id' => $fixture['category']->id,
        'name' => 'Laptop Dell Latitude 5420',
        'brand' => 'Dell',
        'model' => 'Latitude 5420',
        'serial_number' => 'SN-0001',
        'owning_branch_id' => $fixture['branch']->id,
        'current_branch_id' => $fixture['branch']->id,
        'storage_location_id' => $fixture['rack']->id,
        'department_id' => $fixture['it']->id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
        'acquired_at' => '2026-01-15',
        'acquisition_cost' => '15000000',
        'warranty_expires_at' => '2029-01-15',
    ], $overrides);
}

test('kode aset dibuat otomatis dari kategori dan lokasi pemilik', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture))
        ->assertRedirect();

    $asset = Asset::query()->firstOrFail();

    expect($asset->asset_code)->toBe('AST-LPT-HO-'.str_pad((string) $asset->id, 4, '0', STR_PAD_LEFT));
});

test('kode aset tidak berubah meski aset dipindah ke cabang lain', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();
    $code = $asset->asset_code;

    $this->actingAs($admin)->put(route('assets.update', $asset), assetPayload($fixture, [
        'current_branch_id' => $fixture['otherBranch']->id,
        'storage_location_id' => $fixture['otherStorage']->id,
    ]))->assertRedirect();

    expect($asset->fresh()->asset_code)->toBe($code);
});

test('aset bisa dimiliki dua divisi sekaligus', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())->post(route('assets.store'), assetPayload($fixture, [
        'secondary_department_id' => $fixture['ops']->id,
    ]));

    $asset = Asset::query()->firstOrFail();

    expect($asset->departments()->pluck('departments.id')->sort()->values()->all())
        ->toBe(collect([$fixture['it']->id, $fixture['ops']->id])->sort()->values()->all());
});

test('divisi kedua tidak boleh sama dengan divisi pemilik', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture, [
            'secondary_department_id' => $fixture['it']->id,
        ]))
        ->assertSessionHasErrors('secondary_department_id');
});

test('kategori yang mewajibkan nomor seri menolak aset tanpa nomor seri', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture, ['serial_number' => null]))
        ->assertSessionHasErrors('serial_number');
});

test('nomor seri tidak boleh dipakai dua aset', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));

    $this->actingAs($admin)
        ->post(route('assets.store'), assetPayload($fixture, ['name' => 'Laptop kedua']))
        ->assertSessionHasErrors('serial_number');
});

test('status dipegang tidak bisa dipilih dari formulir master', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture, ['status' => AssetStatus::Assigned->value]))
        ->assertSessionHasErrors('status');
});

test('aset yang sudah punya riwayat tidak bisa dihapus', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();

    // Status yang sudah berjalan menutup pintu penghapusan.
    $asset->forceFill(['status' => AssetStatus::Retired->value])->save();

    $this->actingAs($admin)->delete(route('assets.destroy', $asset))->assertRedirect();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
});

test('aset draft masih bisa dihapus', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture, [
        'status' => AssetStatus::Draft->value,
    ]));
    $asset = Asset::query()->firstOrFail();

    $this->actingAs($admin)->delete(route('assets.destroy', $asset))->assertRedirect(route('assets.index'));

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeFalse();
});

test('formulir tambah dan ubah aset bisa dibuka', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->get(route('assets.create'))->assertOk()->assertSee('Divisi Kedua');

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();

    $this->actingAs($admin)->get(route('assets.edit', $asset))->assertOk()->assertSee($asset->asset_code);
    $this->actingAs($admin)->get(route('assets.show', $asset))->assertOk()->assertSee('Berkas Aset');
});

test('status alur kerja ditampilkan sebagai keterangan, bukan pilihan yang bisa diubah', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();
    $asset->forceFill(['status' => AssetStatus::Assigned->value])->save();

    $this->actingAs($admin)->get(route('assets.edit', $asset))
        ->assertOk()
        ->assertSee('Diatur oleh alur kerja');

    // Menyunting data lain tidak boleh diam-diam mengembalikannya ke status manual.
    $this->actingAs($admin)->put(route('assets.update', $asset), assetPayload($fixture, [
        'name' => 'Laptop Dell (diperbarui)',
        'status' => AssetStatus::Available->value,
    ]));

    expect($asset->fresh()->status)->toBe(AssetStatus::Assigned)
        ->and($asset->fresh()->name)->toBe('Laptop Dell (diperbarui)');
});

test('formulir kategori aset bisa dibuka', function () {
    $admin = assetAdmin();

    $this->actingAs($admin)->get(route('assets.categories.index'))->assertOk();
    $this->actingAs($admin)->get(route('assets.categories.create'))->assertOk()->assertSee('Prefix Kode Aset');

    $category = AssetCategory::query()->create([
        'code' => 'MON', 'name' => 'Monitor', 'asset_prefix' => 'MON', 'is_active' => true,
    ]);

    $this->actingAs($admin)->get(route('assets.categories.edit', $category))->assertOk()->assertSee('Monitor');
});

test('divisi yang dinonaktifkan tidak mengunci aset lama dari penyuntingan', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();

    $fixture['it']->forceFill(['is_active' => false])->save();

    $this->actingAs($admin)->get(route('assets.edit', $asset))->assertOk()->assertSee('IT');

    $this->actingAs($admin)
        ->put(route('assets.update', $asset), assetPayload($fixture, ['name' => 'Laptop Dell (diperbaiki)']))
        ->assertRedirect(route('assets.show', $asset));

    expect($asset->fresh()->name)->toBe('Laptop Dell (diperbaiki)')
        ->and($asset->fresh()->department_id)->toBe($fixture['it']->id);
});

test('kode aset terkunci setelah dibuat, bahkan lewat penetapan langsung', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();
    $code = $asset->asset_code;

    // Lapis pertama: pengisian massal membuang kolomnya diam-diam ($fillable),
    // jadi update() biasa tidak pernah sampai menyentuh kodenya.
    $asset->update(['asset_code' => 'AST-XXX-XX-9999', 'name' => 'Nama baru']);

    expect($asset->fresh()->asset_code)->toBe($code)
        ->and($asset->fresh()->name)->toBe('Nama baru');

    // Lapis kedua: penetapan langsung dan forceFill melewati $fillable, tapi tidak
    // melewati penjaga di model.
    expect(function () use ($asset) {
        $asset->asset_code = 'AST-XXX-XX-9999';
        $asset->save();
    })->toThrow(LogicException::class);

    $asset->refresh();

    expect(function () use ($asset) {
        $asset->forceFill(['asset_code' => 'AST-XXX-XX-9999'])->save();
    })->toThrow(LogicException::class);

    $asset->refresh();

    // Mengosongkannya pun bukan jalan keluar.
    expect(function () use ($asset) {
        $asset->forceFill(['asset_code' => null])->save();
    })->toThrow(LogicException::class);

    expect(Asset::query()->whereKey($asset->id)->value('asset_code'))->toBe($code);
});

test('menyimpan ulang aset tidak membangkitkan kode baru', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();
    $code = $asset->asset_code;

    // Kategori dan cabang pemilik ikut membentuk kode — mengubah keduanya sekaligus
    // adalah cara paling mungkin sebuah kode ikut bergeser tanpa disengaja.
    $newCategory = AssetCategory::query()->create([
        'code' => 'MON', 'name' => 'Monitor', 'asset_prefix' => 'MON', 'is_active' => true,
    ]);

    $this->actingAs($admin)->put(route('assets.update', $asset), assetPayload($fixture, [
        'category_id' => $newCategory->id,
        'owning_branch_id' => $fixture['otherBranch']->id,
        'current_branch_id' => $fixture['otherBranch']->id,
        'storage_location_id' => $fixture['otherStorage']->id,
    ]))->assertRedirect();

    expect($asset->fresh()->asset_code)->toBe($code);
});

test('aset berstatus tersedia wajib punya tempat penyimpanan', function () {
    $fixture = assetFixture();

    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture, ['storage_location_id' => null]))
        ->assertSessionHasErrors('storage_location_id');
});

test('status selain tersedia boleh tanpa tempat penyimpanan', function () {
    $fixture = assetFixture();

    // Barangnya memang tidak di gudang: sedang diservis di vendor.
    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture, [
            'status' => AssetStatus::Maintenance->value,
            'storage_location_id' => null,
        ]))
        ->assertRedirect();

    expect(Asset::query()->firstOrFail()->storage_location_id)->toBeNull();
});

test('tempat penyimpanan harus berada di lokasi aset yang dipilih', function () {
    $fixture = assetFixture();

    // Aset di Head Office tidak bisa tersimpan di gudang Surabaya.
    $this->actingAs(assetAdmin())
        ->post(route('assets.store'), assetPayload($fixture, [
            'storage_location_id' => $fixture['otherStorage']->id,
        ]))
        ->assertSessionHasErrors('storage_location_id');
});

test('menyaring per gudang ikut menampilkan isi rak di dalamnya', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    // Satu aset di Rak B (di dalam Gudang A), satu lagi langsung di Lantai 4.
    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture, [
        'name' => 'Laptop di rak',
        'serial_number' => 'SN-RAK',
        'storage_location_id' => $fixture['rack']->id,
    ]));

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture, [
        'name' => 'Laptop di lantai',
        'serial_number' => 'SN-LANTAI',
        'storage_location_id' => $fixture['floor']->id,
    ]));

    $this->actingAs($admin)->get(route('assets.index', ['storage' => $fixture['warehouse']->id]))
        ->assertOk()
        ->assertSee('Laptop di rak')
        ->assertDontSee('Laptop di lantai');

    // Menyaring dari jenjang teratas mencakup keduanya.
    $this->actingAs($admin)->get(route('assets.index', ['storage' => $fixture['floor']->id]))
        ->assertOk()
        ->assertSee('Laptop di rak')
        ->assertSee('Laptop di lantai');
});

test('tempat penyimpanan yang dinonaktifkan tidak mengunci aset lama', function () {
    $fixture = assetFixture();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.store'), assetPayload($fixture));
    $asset = Asset::query()->firstOrFail();

    $fixture['rack']->forceFill(['is_active' => false])->save();

    $this->actingAs($admin)->get(route('assets.edit', $asset))->assertOk()->assertSee('Rak B');

    $this->actingAs($admin)
        ->put(route('assets.update', $asset), assetPayload($fixture, ['name' => 'Laptop (diperbaiki)']))
        ->assertRedirect(route('assets.show', $asset));

    expect($asset->fresh()->storage_location_id)->toBe($fixture['rack']->id);
});
