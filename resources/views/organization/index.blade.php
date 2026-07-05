<x-layouts.app title="Organization - {{ config('app.name', 'HRIS') }}" heading="Organization">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Lokasi kerja, gudang, dan divisi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Organization</h1>
            </div>

            @can('access-control.update')
                <a href="{{ route('access-control.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    Atur Lokasi & Divisi
                </a>
            @endcan
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Lokasi Aktif</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['locations']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Gudang</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['warehouses']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Divisi Aktif</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['departments']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Karyawan Aktif</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['active_employees']) }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Lokasi Kerja & Gudang</h2>
                <p class="mt-1 text-sm text-gray-500">Daftar kantor/gudang beserta divisi yang tersedia dan jumlah karyawan aktif.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Lokasi</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Jenis</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Divisi</th>
                            <th class="px-5 py-3 text-right font-medium text-gray-500">Karyawan Aktif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($branches as $branch)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-gray-950">{{ $branch->name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $branch->city ?? '-' }} · {{ $branch->address ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'rounded-md px-2 py-1 text-xs font-medium',
                                        'bg-gray-950 text-white' => $branch->type === 'warehouse',
                                        'bg-gray-100 text-gray-700' => $branch->type !== 'warehouse',
                                    ])>
                                        {{ $branch->type === 'warehouse' ? 'Gudang' : str($branch->type ?: 'office')->headline() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @forelse ($branch->departments as $department)
                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                                                {{ $department->name }}
                                                @if ($department->pivot->is_primary)
                                                    · Utama
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-sm text-gray-400">Belum ada divisi</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-gray-950">
                                    {{ number_format($branch->active_employees_count) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada lokasi kerja.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Divisi Per Lokasi</h2>
                <p class="mt-1 text-sm text-gray-500">Melihat satu divisi berada di lokasi mana saja, termasuk gudang.</p>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($departments as $department)
                    <article class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-[260px_1fr_180px]">
                        <div>
                            <p class="font-medium text-gray-950">{{ $department->name }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $department->code ?? '-' }} · {{ number_format($department->job_positions_count) }} jabatan</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($department->branches as $branch)
                                <span @class([
                                    'rounded-md px-2 py-1 text-xs font-medium',
                                    'bg-gray-950 text-white' => $branch->type === 'warehouse',
                                    'bg-gray-100 text-gray-700' => $branch->type !== 'warehouse',
                                ])>
                                    {{ $branch->name }}
                                </span>
                            @empty
                                <span class="text-sm text-gray-400">Belum ditempatkan di lokasi mana pun</span>
                            @endforelse
                        </div>
                        <div class="text-left lg:text-right">
                            <p class="text-sm text-gray-500">Karyawan aktif</p>
                            <p class="mt-1 text-xl font-semibold text-gray-950">{{ number_format($department->active_employees_count) }}</p>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-gray-500">Belum ada divisi.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
