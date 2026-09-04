<x-layouts.app title="Kategori Aset - {{ config('app.name', 'HRIS') }}" heading="Kategori Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master aset</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Kategori Aset</h1>
                <p class="mt-1 text-sm text-gray-500">Prefix kategori ikut membentuk kode aset, misalnya <span class="font-mono text-gray-700">AST-LPT-HO-0012</span>.</p>
            </div>
            @can('asset-categories.create')
                <a href="{{ route('assets.categories.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah Kategori</a>
            @endcan
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('assets.categories.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_160px_auto_auto]">
                <div>
                    <label for="category_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="category_search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari kode, nama, prefix">
                </div>
                <div>
                    <label for="category_per_page" class="block text-sm font-medium text-gray-700">Per halaman</label>
                    <select id="category_per_page" name="per_page" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected(($perPage ?? 15) === $option)>{{ $option }} / halaman</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('assets.categories.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Prefix</th>
                            <th>Nomor Seri</th>
                            <th>Umur Ekonomis</th>
                            <th>Status</th>
                            <th class="text-right">Aset</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-950">{{ $category->name }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $category->code }}</p>
                                </td>
                                <td class="font-mono text-xs">{{ $category->asset_prefix }}</td>
                                <td>{{ $category->requires_serial ? 'Wajib' : 'Opsional' }}</td>
                                <td>{{ $category->useful_life_label ?? '-' }}</td>
                                <td>
                                    <x-status-badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                                </td>
                                <td class="text-right">{{ number_format($category->assets_count) }}</td>
                                <td class="text-right">
                                    @canany(['asset-categories.update', 'asset-categories.delete'])
                                        <x-action-menu>
                                            @can('asset-categories.update')
                                                <a href="{{ route('assets.categories.edit', $category) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                            @endcan
                                            @can('asset-categories.delete')
                                                <form method="POST" action="{{ route('assets.categories.destroy', $category) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                                </form>
                                            @endcan
                                        </x-action-menu>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada kategori aset.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $categories->links() }}</div>
        </section>
    </div>
</x-layouts.app>
