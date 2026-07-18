<x-layouts.app title="Kontrak Karyawan - {{ config('app.name', 'HRIS') }}" heading="Kontrak Karyawan">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Employee module</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Kontrak Karyawan</h1>
                <p class="mt-1 text-sm text-gray-500">Pantau kontrak berjalan, yang akan berakhir, dan yang sudah kedaluwarsa. Perpanjangan dilakukan dari halaman karyawan.</p>
            </div>

            @can('employees.export')
                <a href="{{ route('employees.contracts.export', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    <x-icon name="download" class="size-4"/> Export Excel
                </a>
            @endcan
        </section>

        @php
            $activeFilter = $filters['filter'] ?? 'all';
            // Preserve the other active filters (lokasi/divisi/jenis/cari), only swap the
            // range dimension the card represents, and reset pagination.
            $cardUrl = fn (string $filter) => request()->fullUrlWithQuery(['filter' => $filter === 'all' ? null : $filter, 'page' => null]);
        @endphp

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <x-stat-card label="Total Kontrak" :value="number_format($summary['total'])" tone="primary"
                :href="$cardUrl('all')" :active="$activeFilter === 'all'" hint="Semua kontrak">
                <x-icon name="layers" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Kontrak Aktif" :value="number_format($summary['active'])" tone="emerald"
                :href="$cardUrl('active')" :active="$activeFilter === 'active'" hint="Sedang berjalan">
                <x-icon name="user-check" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Habis ≤30 Hari" :value="number_format($summary['expiring_30'])" tone="amber"
                :href="$cardUrl('expiring_30')" :active="$activeFilter === 'expiring_30'" hint="Segera berakhir">
                <x-icon name="calendar-clock" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Habis ≤60 Hari" :value="number_format($summary['expiring_60'])" tone="amber"
                :href="$cardUrl('expiring_60')" :active="$activeFilter === 'expiring_60'" hint="Berakhir 60 hari">
                <x-icon name="calendar-clock" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Habis ≤90 Hari" :value="number_format($summary['expiring_90'])" tone="amber"
                :href="$cardUrl('expiring_90')" :active="$activeFilter === 'expiring_90'" hint="Berakhir 90 hari">
                <x-icon name="calendar-clock" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Kedaluwarsa" :value="number_format($summary['expired'])" tone="rose"
                :href="$cardUrl('expired')" :active="$activeFilter === 'expired'" hint="Lewat & belum diperbarui">
                <x-icon name="alert-triangle" class="size-5"/>
            </x-stat-card>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('employees.contracts.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Nama, kode karyawan, nomor kontrak">
                </div>
                <div>
                    <label for="filter" class="block text-sm font-medium text-gray-700">Rentang / Status</label>
                    <select id="filter" name="filter" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="all" @selected($activeFilter === 'all')>Semua kontrak</option>
                        <option value="active" @selected($activeFilter === 'active')>Aktif</option>
                        <option value="expiring_30" @selected($activeFilter === 'expiring_30')>Akan habis ≤30 hari</option>
                        <option value="expiring_60" @selected($activeFilter === 'expiring_60')>Akan habis ≤60 hari</option>
                        <option value="expiring_90" @selected($activeFilter === 'expiring_90')>Akan habis ≤90 hari</option>
                        <option value="expired" @selected($activeFilter === 'expired')>Kedaluwarsa</option>
                    </select>
                </div>
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi</label>
                    <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi</label>
                    <select id="department_id" name="department_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Jenis</label>
                    <select id="type" name="type" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua jenis</option>
                        @foreach ($contractTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-full flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:w-auto">Filter</button>
                    <a href="{{ route('employees.contracts.index') }}" class="w-full rounded-md border border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Daftar Kontrak</h2>
                <p class="mt-1 text-sm text-gray-500">Diurutkan dari yang paling dekat masa berakhirnya.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th>Lokasi / Divisi</th>
                            <th>Nomor Kontrak</th>
                            <th>Jenis</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contracts as $contract)
                            <tr>
                                <td>
                                    @if ($contract->employee)
                                        <a href="{{ route('employees.show', $contract->employee) }}" class="font-medium text-gray-950 hover:underline">{{ $contract->employee->full_name ?? 'Tanpa nama' }}</a>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $contract->employee->employee_number ?? 'Kode belum dibuat' }}</p>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="text-sm">
                                    <p class="text-gray-800">{{ $contract->employee?->branch?->name ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $contract->employee?->departments->pluck('name')->implode(', ') ?: '-' }}</p>
                                </td>
                                <td class="text-sm font-medium text-gray-800">{{ $contract->contract_number }}</td>
                                <td class="text-sm text-gray-700">{{ $contract->contract_type }}</td>
                                <td class="text-sm text-gray-600">
                                    {{ $contract->start_date?->format('d M Y') ?? '-' }} – {{ $contract->end_date?->format('d M Y') ?? 'Tidak terbatas' }}
                                </td>
                                <td>
                                    <x-status-badge :tone="$contract->effective_status_tone">{{ $contract->effective_status_label }}</x-status-badge>
                                </td>
                                <td class="text-sm">
                                    @php $remaining = $contract->remaining_days; @endphp
                                    @if ($contract->status !== 'active' || is_null($remaining))
                                        <span class="text-gray-400">—</span>
                                    @elseif ($remaining < 0)
                                        <span class="font-medium text-red-600">Lewat {{ abs($remaining) }} hari</span>
                                    @else
                                        <span @class(['font-medium', 'text-amber-600' => $remaining <= 30, 'text-gray-700' => $remaining > 30])>{{ $remaining }} hari</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Tidak ada kontrak yang cocok dengan filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $contracts->links() }}
            </div>
        </section>
    </div>
</x-layouts.app>
