<x-layouts.app title="Lokasi Penyimpanan Aset - {{ config('app.name', 'HRIS') }}" heading="Lokasi Penyimpanan Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master aset</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Lokasi Penyimpanan</h1>
                <p class="mt-1 text-sm text-gray-500">Tersusun bertingkat di dalam tiap lokasi kerja, misalnya <span class="font-medium">Lantai 4 › Gudang A › Rak B</span> atau <span class="font-medium">Lantai 2 › Ruang Office A</span>.</p>
            </div>
            @can('asset-storage-locations.create')
                <a href="{{ route('assets.storage-locations.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah Tempat</a>
            @endcan
        </section>

        <x-scope-notice :has-no-scope="$hasNoScope" />

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('assets.storage-locations.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_220px_auto_auto]">
                <div>
                    <label for="storage_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="storage_search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari nama tempat atau kode">
                </div>
                <div>
                    <label for="storage_branch" class="block text-sm font-medium text-gray-700">Lokasi Kerja</label>
                    <select id="storage_branch" name="branch" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="self-end rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('assets.storage-locations.index') }}" class="self-end rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tempat</th>
                            <th>Lokasi Kerja</th>
                            <th>Kode</th>
                            <th>Status</th>
                            <th class="text-right">Isi</th>
                            <th class="text-right">Aset</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            <tr>
                                <td>
                                    {{-- Diberi jarak menurut jenjangnya supaya susunan pohonnya terbaca
                                         sekilas; jalur lengkapnya tetap ditulis di bawah agar baris yang
                                         terpotong pada layar sempit masih bisa dikenali. --}}
                                    <p class="font-medium text-gray-950" style="padding-left: {{ $location->depth * 16 }}px">
                                        @if ($location->depth > 0)<span class="mr-1 text-gray-300">└</span>@endif
                                        {{ $location->name }}
                                    </p>
                                    @if ($location->depth > 0)
                                        <p class="mt-0.5 text-xs text-gray-500" style="padding-left: {{ $location->depth * 16 }}px">{{ $location->full_path }}</p>
                                    @endif
                                </td>
                                <td>{{ $location->branch?->name ?? '-' }}</td>
                                <td class="font-mono text-xs">{{ $location->code ?: '-' }}</td>
                                <td><x-status-badge :tone="$location->is_active ? 'success' : 'neutral'">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge></td>
                                <td class="text-right">{{ number_format($location->children_count) }}</td>
                                <td class="text-right">{{ number_format($location->assets_count) }}</td>
                                <td class="text-right">
                                    @canany(['asset-storage-locations.update', 'asset-storage-locations.delete'])
                                        <x-action-menu>
                                            @can('asset-storage-locations.update')
                                                <a href="{{ route('assets.storage-locations.edit', $location) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                            @endcan
                                            @can('asset-storage-locations.delete')
                                                @if ($location->children_count === 0 && $location->assets_count === 0)
                                                    <form method="POST" action="{{ route('assets.storage-locations.destroy', $location) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </x-action-menu>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada tempat penyimpanan. Mulai dari jenjang teratas, misalnya "Lantai 4".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $locations->links() }}</div>
        </section>
    </div>
</x-layouts.app>
