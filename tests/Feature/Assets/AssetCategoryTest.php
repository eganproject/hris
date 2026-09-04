<?php

use App\Enums\AssetStatus;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStorageLocation;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kode dan prefix kategori disimpan dalam huruf besar', function () {
    $this->actingAs(assetAdmin())
        ->post(route('assets.categories.store'), [
            'code' => 'laptop', 'name' => 'Laptop', 'asset_prefix' => 'lpt', 'is_active' => '1',
        ])
        ->assertRedirect(route('assets.categories.index'));

    $category = AssetCategory::query()->firstOrFail();

    expect($category->code)->toBe('LAPTOP')
        ->and($category->asset_prefix)->toBe('LPT');
});

test('prefix kategori menolak tanda baca yang mengaburkan kode aset', function () {
    $this->actingAs(assetAdmin())
        ->post(route('assets.categories.store'), [
            'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LP-T',
        ])
        ->assertSessionHasErrors('asset_prefix');
});

test('kategori yang masih dipakai aset tidak bisa dihapus', function () {
    $category = AssetCategory::query()->create([
        'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true,
    ]);
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);

    Asset::query()->create([
        'category_id' => $category->id,
        'name' => 'Laptop HO',
        'owning_branch_id' => $branch->id,
        'current_branch_id' => $branch->id,
        'department_id' => $department->id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ]);

    $this->actingAs(assetAdmin())
        ->delete(route('assets.categories.destroy', $category))
        ->assertRedirect(route('assets.categories.index'))
        ->assertSessionHas('error');

    expect(AssetCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('perubahan master aset tercatat di jejak aktivitas modul aset', function () {
    $this->actingAs(assetAdmin())->post(route('assets.categories.store'), [
        'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => '1',
    ]);

    $log = ActivityLog::query()->where('module', 'assets')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('created')
        ->and($log->subject_label)->toBe('LAPTOP — Laptop');
});

test('jejak aktivitas aset menyebut kode asetnya', function () {
    $category = AssetCategory::query()->create([
        'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true,
    ]);
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);

    $storage = AssetStorageLocation::query()->create([
        'branch_id' => $branch->id, 'name' => 'Gudang A', 'is_active' => true,
    ]);

    $this->actingAs(assetAdmin())->post(route('assets.store'), [
        'category_id' => $category->id,
        'name' => 'Laptop Dell',
        'owning_branch_id' => $branch->id,
        'current_branch_id' => $branch->id,
        'storage_location_id' => $storage->id,
        'department_id' => $department->id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ])->assertRedirect();

    $asset = Asset::query()->firstOrFail();
    $log = ActivityLog::query()->where('subject_type', Asset::class)->latest('id')->first();

    expect($log?->subject_label)->toBe("{$asset->asset_code} — Laptop Dell");
});
