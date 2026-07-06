<x-layouts.app title="Lokasi Kerja - {{ config('app.name', 'HRIS') }}" heading="Lokasi Kerja">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master organisasi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Lokasi Kerja</h1>
            </div>
            @can('organization.create')
                <a href="{{ route('organization.branches.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah Lokasi</a>
            @endcan
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('organization.branches.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_160px_auto_auto]">
                <div>
                    <label for="branch_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="branch_search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari kode, nama, kota">
                </div>
                <div>
                    <label for="branch_per_page" class="block text-sm font-medium text-gray-700">Per halaman</label>
                    <select id="branch_per_page" name="per_page" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected(($perPage ?? 15) === $option)>{{ $option }} / halaman</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('organization.branches.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lokasi</th>
                            <th>Jenis</th>
                            <th>Kota</th>
                            <th class="text-right">Divisi</th>
                            <th class="text-right">Karyawan Aktif</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-950">{{ $branch->name }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $branch->code }}</p>
                                </td>
                                <td>{{ $branch->type === 'warehouse' ? 'Gudang' : 'Office' }}</td>
                                <td>{{ $branch->city ?? '-' }}</td>
                                <td class="text-right">{{ number_format($branch->departments_count) }}</td>
                                <td class="text-right">{{ number_format($branch->active_employees_count) }}</td>
                                <td class="text-right">
                                    @canany(['organization.update', 'organization.delete'])
                                        <x-action-menu>
                                            @can('organization.update')
                                                <a href="{{ route('organization.branches.edit', $branch) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                            @endcan
                                            @can('organization.delete')
                                                <form method="POST" action="{{ route('organization.branches.destroy', $branch) }}">
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
                            <tr><td colspan="6" class="cell-empty">Belum ada lokasi kerja.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $branches->links() }}</div>
        </section>
    </div>
</x-layouts.app>
