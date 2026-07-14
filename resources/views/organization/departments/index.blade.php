<x-layouts.app title="Divisi - {{ config('app.name', 'HRIS') }}" heading="Divisi">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master organisasi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Divisi</h1>
            </div>
            @can('departments.create')
                <a href="{{ route('organization.departments.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah Divisi</a>
            @endcan
        </section>


        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('organization.departments.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_160px_auto_auto]">
                <div>
                    <label for="department_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="department_search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari kode atau nama divisi">
                </div>
                <div>
                    <label for="department_per_page" class="block text-sm font-medium text-gray-700">Per halaman</label>
                    <select id="department_per_page" name="per_page" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected(($perPage ?? 15) === $option)>{{ $option }} / halaman</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('organization.departments.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Divisi</th>
                            <th>Deskripsi</th>
                            <th class="text-right">Lokasi</th>
                            <th class="text-right">Jabatan</th>
                            <th class="text-right">Karyawan Aktif</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $department->name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $department->code }}</p></td>
                                <td>{{ $department->description ?? '-' }}</td>
                                <td class="text-right">{{ number_format($department->branches_count) }}</td>
                                <td class="text-right">{{ number_format($department->job_positions_count) }}</td>
                                <td class="text-right">{{ number_format($department->active_employees_count) }}</td>
                                <td class="text-right">
                                    @canany(['departments.update', 'departments.delete'])
                                        <x-action-menu>
                                            @can('departments.update')
                                                <a href="{{ route('organization.departments.edit', $department) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                            @endcan
                                            @can('departments.delete')
                                                <form method="POST" action="{{ route('organization.departments.destroy', $department) }}">
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
                            <tr><td colspan="6" class="cell-empty">Belum ada divisi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $departments->links() }}</div>
        </section>
    </div>
</x-layouts.app>
