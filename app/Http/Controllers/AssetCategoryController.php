<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetCategoryRequest;
use App\Models\AssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master kategori aset. Tidak dibatasi cakupan lokasi/divisi: kategori adalah
 * kosakata bersama seluruh perusahaan — "Laptop" tidak berbeda antar cabang.
 */
class AssetCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $categories = AssetCategory::query()
            ->withCount('assets')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('asset_prefix', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('assets.categories.index', [
            'categories' => $categories,
            'filters' => $request->only('search'),
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('assets.categories.create', [
            'category' => new AssetCategory(['is_active' => true, 'requires_serial' => false]),
        ]);
    }

    public function store(AssetCategoryRequest $request): RedirectResponse
    {
        AssetCategory::query()->create($request->payload());

        return redirect()->route('assets.categories.index')->with('status', 'Kategori aset berhasil dibuat.');
    }

    public function edit(AssetCategory $category): View
    {
        return view('assets.categories.edit', ['category' => $category]);
    }

    public function update(AssetCategoryRequest $request, AssetCategory $category): RedirectResponse
    {
        $category->update($request->payload());

        return redirect()->route('assets.categories.index')->with('status', 'Kategori aset berhasil diperbarui.');
    }

    /**
     * Kategori ikut membentuk kode aset yang sudah tercetak dan tertempel di barang.
     * Menghapusnya selagi masih dipakai akan menyisakan aset yang kodenya tidak lagi
     * bisa dijelaskan — jadi yang sudah tidak dipakai cukup dinonaktifkan.
     */
    public function destroy(AssetCategory $category): RedirectResponse
    {
        $assets = $category->assets()->withTrashed()->count();

        if ($assets > 0) {
            return redirect()->route('assets.categories.index')
                ->with('error', "Kategori \"{$category->name}\" masih dipakai {$assets} aset. Nonaktifkan kategorinya bila sudah tidak dipakai lagi.");
        }

        $category->delete();

        return redirect()->route('assets.categories.index')->with('status', 'Kategori aset berhasil dihapus.');
    }
}
