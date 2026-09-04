<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDocument;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function documentAsset(): Asset
{
    $category = AssetCategory::query()->create([
        'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true,
    ]);
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);

    return Asset::query()->create([
        'category_id' => $category->id,
        'name' => 'Laptop HO',
        'owning_branch_id' => $branch->id,
        'current_branch_id' => $branch->id,
        'department_id' => $department->id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ])->refresh();
}

test('berkas aset tersimpan di disk privat', function () {
    Storage::fake('local');
    $asset = documentAsset();

    $this->actingAs(assetAdmin())
        ->post(route('assets.documents.store', $asset), [
            'type' => 'invoice',
            'title' => 'Faktur pembelian',
            'file' => UploadedFile::fake()->create('faktur.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('assets.show', $asset));

    $document = AssetDocument::query()->firstOrFail();

    expect($document->asset_id)->toBe($asset->id)
        ->and($document->disk)->toBe('local')
        ->and($document->path)->toStartWith("asset-documents/{$asset->id}/");

    Storage::disk('local')->assertExists($document->path);
});

test('berkas hanya bisa dibuka lewat rute berotorisasi', function () {
    Storage::fake('local');
    $asset = documentAsset();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.documents.store', $asset), [
        'type' => 'invoice',
        'file' => UploadedFile::fake()->create('faktur.pdf', 120, 'application/pdf'),
    ]);

    $document = AssetDocument::query()->firstOrFail();

    $this->actingAs($admin)->get(route('assets.documents.show', [$asset, $document]))->assertOk();

    // Pengguna di luar cakupan aset ini tidak boleh menembus lewat URL berkasnya.
    $outsider = scopedAssetOfficer();
    $this->actingAs($outsider)->get(route('assets.documents.show', [$asset, $document]))->assertForbidden();
});

test('berkas milik aset lain tidak ikut terbuka lewat url aset yang boleh dilihat', function () {
    Storage::fake('local');
    $asset = documentAsset();
    $admin = assetAdmin();

    $other = Asset::query()->create([
        'category_id' => $asset->category_id,
        'name' => 'Laptop kedua',
        'owning_branch_id' => $asset->owning_branch_id,
        'current_branch_id' => $asset->current_branch_id,
        'department_id' => $asset->department_id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ]);

    $this->actingAs($admin)->post(route('assets.documents.store', $other), [
        'type' => 'invoice',
        'file' => UploadedFile::fake()->create('faktur.pdf', 120, 'application/pdf'),
    ]);

    $document = AssetDocument::query()->firstOrFail();

    $this->actingAs($admin)->get(route('assets.documents.show', [$asset, $document]))->assertNotFound();
});

test('berkas dengan jenis yang tidak diterima ditolak', function () {
    Storage::fake('local');
    $asset = documentAsset();

    $this->actingAs(assetAdmin())
        ->post(route('assets.documents.store', $asset), [
            'type' => 'invoice',
            'file' => UploadedFile::fake()->create('script.exe', 20, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors('file');

    expect(AssetDocument::query()->count())->toBe(0);
});

test('menghapus berkas ikut membuang filenya dari disk', function () {
    Storage::fake('local');
    $asset = documentAsset();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.documents.store', $asset), [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('kondisi.jpg'),
    ]);

    $document = AssetDocument::query()->firstOrFail();
    $path = $document->path;

    $this->actingAs($admin)->delete(route('assets.documents.destroy', [$asset, $document]))->assertRedirect();

    Storage::disk('local')->assertMissing($path);
    expect(AssetDocument::query()->count())->toBe(0);
});

test('aset yang sudah punya berkas tidak bisa dihapus', function () {
    Storage::fake('local');
    $asset = documentAsset();
    $admin = assetAdmin();

    $this->actingAs($admin)->post(route('assets.documents.store', $asset), [
        'type' => 'invoice',
        'file' => UploadedFile::fake()->create('faktur.pdf', 120, 'application/pdf'),
    ]);

    $this->actingAs($admin)->delete(route('assets.destroy', $asset))->assertRedirect(route('assets.index'));

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
});
