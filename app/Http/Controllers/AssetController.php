<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Exports\AssetsExport;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Master aset: registrasi dan penelusuran barang milik perusahaan.
 *
 * Cakupannya sama dengan modul lain — lokasi kerja & divisi yang ditetapkan di
 * Kontrol Akses — hanya saja dibaca dari kolom di baris asetnya sendiri, bukan lewat
 * daftar karyawan. Lihat DataScope::forAssets().
 */
class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $scope = DataScope::forAssets($request->user());
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $filters = [
            'search' => $request->string('search')->toString(),
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            'condition' => $request->input('condition'),
            'branch' => $request->input('branch'),
            'department' => $request->input('department'),
            'warranty' => $request->input('warranty'),
        ];

        $assets = $scope->assets()
            ->matchingFilters($filters)
            ->with(['category:id,name', 'currentBranch:id,name', 'owningBranch:id,name', 'department:id,name'])
            ->withHistoryFlags()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('assets.index', [
            'assets' => $assets,
            'filters' => $filters,
            'perPage' => $perPage,
            'summary' => $this->summary($scope, $filters),
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name']),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'statuses' => AssetStatus::labels(),
            'conditions' => AssetCondition::labels(),
            'hasNoScope' => $scope->isEmpty(),
            // Akun yang dipersempit ke bawahan belum punya aset yang bisa dilihat
            // selama penyerahan aset belum ada — halaman kosongnya perlu menjelaskan
            // itu, bukan membiarkan orang mengira aplikasinya rusak.
            'limitedToSubordinates' => $request->user()->isLimitedToSubordinates(),
        ]);
    }

    /**
     * Angka ringkas di atas daftar. Dihitung dari cakupan + filter yang sama dengan
     * tabelnya, supaya kartu dan isi tabel tidak pernah bercerita berbeda.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    private function summary(DataScope $scope, array $filters): array
    {
        $base = fn () => $scope->assets()->matchingFilters($filters);

        return [
            'total' => $base()->count(),
            'available' => $base()->where('status', AssetStatus::Available->value)->count(),
            'assigned' => $base()->where('status', AssetStatus::Assigned->value)->count(),
            'maintenance' => $base()->where('status', AssetStatus::Maintenance->value)->count(),
            'warranty_expiring' => $base()
                ->whereNotNull('warranty_expires_at')
                ->whereBetween('warranty_expires_at', [today(), today()->addDays(30)])
                ->count(),
            'value' => (string) ($base()->sum('acquisition_cost') ?: 0),
        ];
    }

    public function create(Request $request): View
    {
        $scope = DataScope::forAssets($request->user());

        return view('assets.create', [
            'asset' => new Asset([
                'status' => AssetStatus::Draft,
                'condition' => AssetCondition::New,
            ]),
            'secondaryDepartmentId' => null,
            ...$this->formData($scope),
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $asset = DB::transaction(function () use ($request): Asset {
            $asset = Asset::query()->create([
                ...$request->payload(),
                'created_by' => $request->user()->id,
            ]);

            $asset->departments()->sync($request->departmentIds());

            return $asset;
        });

        return redirect()->route('assets.show', $asset)
            ->with('status', "Aset {$asset->refresh()->asset_code} berhasil didaftarkan.");
    }

    public function show(Request $request, Asset $asset): View
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        $asset->load([
            'category',
            'owningBranch:id,name,code',
            'currentBranch:id,name,code',
            'department:id,name',
            'departments:id,name',
            'creator:id,name',
            'documents' => fn ($query) => $query->with('uploader:id,name')->latest('id'),
        ]);

        return view('assets.show', ['asset' => $asset]);
    }

    public function edit(Request $request, Asset $asset): View
    {
        $scope = DataScope::forAssets($request->user());
        $scope->authorizeAsset($asset);

        $asset->load('departments:id,name');

        $formData = $this->formData($scope);

        // Divisi yang sudah melekat pada aset tetap tampil di pilihan meski sudah
        // dinonaktifkan, supaya menyimpan ulang tidak diam-diam melepasnya.
        $formData['departments'] = $formData['departments']
            ->concat($asset->departments)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('assets.edit', [
            'asset' => $asset,
            // Divisi kedua adalah anggota himpunan yang bukan divisi utama.
            'secondaryDepartmentId' => collect($asset->departmentIds())
                ->first(fn (int $id) => $id !== (int) $asset->department_id),
            ...$formData,
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        abort_if((bool) $asset->status?->isClosed(), 403, 'Aset yang sudah dilepas hanya bisa dibaca.');

        DB::transaction(function () use ($request, $asset): void {
            $asset->update($request->payload());
            $asset->departments()->sync($request->departmentIds());
        });

        return redirect()->route('assets.show', $asset)->with('status', 'Data aset berhasil diperbarui.');
    }

    /**
     * Menghapus aset hanya boleh selagi ia belum menjadi apa-apa. Yang sudah punya
     * berkas atau sudah berjalan diakhiri lewat status "Tidak Dipakai" — riwayat dan
     * nilai perolehannya tetap ada di pembukuan.
     */
    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        DataScope::forAssets($request->user())->authorizeAsset($asset);

        if (! $asset->canBeDeleted()) {
            return redirect()->route('assets.index')->with(
                'error',
                "Aset {$asset->asset_code} sudah memiliki berkas atau riwayat, jadi tidak bisa dihapus. Ubah statusnya menjadi \"Tidak Dipakai\" agar catatannya tetap tersimpan.",
            );
        }

        $asset->delete();

        return redirect()->route('assets.index')->with('status', "Aset {$asset->asset_code} berhasil dihapus.");
    }

    /**
     * Ekspor mengikuti cakupan DAN filter yang sedang tampil — berkas yang diunduh
     * tidak boleh berisi baris yang tidak boleh dilihat pengunduhnya.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['search', 'category', 'status', 'condition', 'branch', 'department', 'warranty']);

        return Excel::download(
            new AssetsExport($filters, $request->user()),
            'daftar-aset-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * Pilihan yang sama untuk formulir tambah maupun ubah.
     *
     * @return array<string, mixed>
     */
    private function formData(DataScope $scope): array
    {
        return [
            'categories' => AssetCategory::query()->active()->orderBy('name')->get(['id', 'name', 'requires_serial']),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'statuses' => AssetStatus::manualLabels(),
            'conditions' => AssetCondition::labels(),
        ];
    }
}
