<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetStorageLocationRequest;
use App\Models\AssetStorageLocation;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master tempat penyimpanan aset, tersusun bertingkat di dalam tiap lokasi kerja
 * ("Lantai 4 › Gudang A › Rak B").
 *
 * Cakupannya mengikuti lokasi kerja pengguna, sama seperti daftar aset: seorang
 * officer cabang tidak perlu — dan tidak boleh — mengatur rak di gudang cabang lain.
 */
class AssetStorageLocationController extends Controller
{
    public function index(Request $request): View
    {
        $scope = DataScope::forAssets($request->user());
        $branches = $scope->branches();

        $locations = AssetStorageLocation::query()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->when($request->input('branch'), fn ($query, $id) => $query->where('branch_id', $id))
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_path', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->with('branch:id,name')
            ->withCount(['children', 'assets'])
            ->ordered()
            ->paginate(50)
            ->withQueryString();

        return view('assets.storage-locations.index', [
            'locations' => $locations,
            'branches' => $branches,
            'filters' => ['branch' => $request->input('branch'), 'search' => $request->input('search')],
            'hasNoScope' => $scope->isEmpty(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('assets.storage-locations.create', [
            'storageLocation' => new AssetStorageLocation(['is_active' => true]),
            ...$this->formData($request),
        ]);
    }

    public function store(AssetStorageLocationRequest $request): RedirectResponse
    {
        AssetStorageLocation::query()->create($request->payload());

        return redirect()->route('assets.storage-locations.index')
            ->with('status', 'Tempat penyimpanan berhasil dibuat.');
    }

    public function edit(Request $request, AssetStorageLocation $storageLocation): View
    {
        $this->authorizeBranch($request, $storageLocation);

        return view('assets.storage-locations.edit', [
            'storageLocation' => $storageLocation,
            ...$this->formData($request, $storageLocation),
        ]);
    }

    public function update(AssetStorageLocationRequest $request, AssetStorageLocation $storageLocation): RedirectResponse
    {
        $this->authorizeBranch($request, $storageLocation);

        $storageLocation->update($request->payload());

        return redirect()->route('assets.storage-locations.index')
            ->with('status', 'Tempat penyimpanan berhasil diperbarui.');
    }

    /**
     * Menghapus tempat yang masih berisi akan menyisakan rak menggantung atau aset
     * tanpa keterangan posisi. Yang sudah tidak dipakai cukup dinonaktifkan.
     */
    public function destroy(Request $request, AssetStorageLocation $storageLocation): RedirectResponse
    {
        $this->authorizeBranch($request, $storageLocation);

        $children = $storageLocation->children()->count();
        $assets = $storageLocation->assets()->count();

        if ($children > 0 || $assets > 0) {
            $reason = $children > 0
                ? "masih memuat {$children} tempat penyimpanan di dalamnya"
                : "masih menjadi tempat penyimpanan {$assets} aset";

            return redirect()->route('assets.storage-locations.index')->with(
                'error',
                "\"{$storageLocation->full_path}\" {$reason}, jadi tidak bisa dihapus. Nonaktifkan saja bila sudah tidak dipakai.",
            );
        }

        $storageLocation->delete();

        return redirect()->route('assets.storage-locations.index')
            ->with('status', 'Tempat penyimpanan berhasil dihapus.');
    }

    private function authorizeBranch(Request $request, AssetStorageLocation $location): void
    {
        $allowed = DataScope::forAssets($request->user())->branches()->pluck('id')->all();

        abort_unless(in_array($location->branch_id, $allowed, true), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?AssetStorageLocation $location = null): array
    {
        $branches = DataScope::forAssets($request->user())->branches();

        // Calon induk: seluruh tempat di cabang yang boleh diakses, dikurangi baris
        // ini sendiri beserta keturunannya (yang akan membuat pohonnya melingkar)
        // dan yang jenjangnya sudah mentok.
        $excluded = $location ? $location->subtreeIds() : [];

        $parents = AssetStorageLocation::query()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->whereNotIn('id', $excluded ?: [0])
            ->where('depth', '<', AssetStorageLocation::MAX_DEPTH - 1)
            ->ordered()
            ->get(['id', 'branch_id', 'full_path', 'depth']);

        return ['branches' => $branches, 'parents' => $parents];
    }
}
