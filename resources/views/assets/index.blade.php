<x-layouts.app title="Daftar Aset - {{ config('app.name', 'HRIS') }}" heading="Daftar Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Manajemen aset</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Daftar Aset</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('assets.export')
                    <a href="{{ route('assets.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><x-icon name="download"/> Ekspor</a>
                @endcan
                @can('asset-categories.view')
                    <a href="{{ route('assets.categories.index') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><x-icon name="layers"/> Kategori</a>
                @endcan
                @can('assets.create')
                    <a href="{{ route('assets.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah Aset</a>
                @endcan
            </div>
        </section>

@php $branchNames = $branches->pluck('name', 'id'); @endphp

        <x-scope-notice :has-no-scope="$hasNoScope" />

        @if ($limitedToSubordinates && ! $hasNoScope)
            {{-- Bukan halaman rusak: akun ini memang dipersempit ke bawahannya, dan
                 aset baru terhubung ke orang setelah modul penyerahan aktif. --}}
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Akun Anda dibatasi ke bawahan, sehingga daftar aset mengikuti siapa yang memegang asetnya. Penyerahan aset kepada karyawan belum tersedia pada tahap ini, jadi belum ada aset yang bisa ditampilkan di sini. Untuk melihat aset per lokasi kerja, minta admin mengubah cakupan akun Anda di menu <span class="font-medium">Kontrol Akses</span>.
            </div>
        @endif

        <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-stat-card label="Total aset" :value="number_format($summary['total'])" tone="primary"><x-icon name="box"/></x-stat-card>
            <x-stat-card label="Tersedia" :value="number_format($summary['available'])" tone="emerald"><x-icon name="box"/></x-stat-card>
            <x-stat-card label="Dipegang" :value="number_format($summary['assigned'])" tone="sky"><x-icon name="user-check"/></x-stat-card>
            <x-stat-card label="Perawatan" :value="number_format($summary['maintenance'])" tone="amber"><x-icon name="refresh"/></x-stat-card>
            <x-stat-card label="Nilai perolehan" :value="'Rp '.number_format((float) $summary['value'], 0, ',', '.')" tone="violet" :hint="$summary['warranty_expiring'].' aset garansi ≤30 hari'"><x-icon name="banknote"/></x-stat-card>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('assets.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label for="asset_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="asset_search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Kode aset, nama, nomor seri, merek">
                </div>
                <div>
                    <label for="asset_category" class="block text-sm font-medium text-gray-700">Kategori</label>
                    <select id="asset_category" name="category" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asset_status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="asset_status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asset_condition" class="block text-sm font-medium text-gray-700">Kondisi</label>
                    <select id="asset_condition" name="condition" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua kondisi</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['condition'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asset_branch" class="block text-sm font-medium text-gray-700">Lokasi</label>
                    <select id="asset_branch" name="branch" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asset_department" class="block text-sm font-medium text-gray-700">Divisi</label>
                    <select id="asset_department" name="department" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asset_storage" class="block text-sm font-medium text-gray-700">Penyimpanan</label>
                    <select id="asset_storage" name="storage" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua tempat</option>
                        @foreach ($storageLocations as $location)
                            <option value="{{ $location->id }}" @selected((string) ($filters['storage'] ?? '') === (string) $location->id)>{{ $branchNames[$location->branch_id] ?? '' }} › {{ $location->full_path }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Memilih gudang ikut menampilkan isi rak di dalamnya.</p>
                </div>
                <div>
                    <label for="asset_warranty" class="block text-sm font-medium text-gray-700">Garansi</label>
                    <select id="asset_warranty" name="warranty" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua</option>
                        <option value="expiring" @selected(($filters['warranty'] ?? '') === 'expiring')>Berakhir ≤ 30 hari</option>
                        <option value="expired" @selected(($filters['warranty'] ?? '') === 'expired')>Sudah berakhir</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('assets.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Aset</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Divisi</th>
                            <th>Status</th>
                            <th>Kondisi</th>
                            <th>Garansi</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td>
                                    <a href="{{ route('assets.show', $asset) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $asset->name }}</a>
                                    <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $asset->asset_code }}</p>
                                    @if ($asset->serial_number)
                                        <p class="mt-0.5 text-xs text-gray-400">SN {{ $asset->serial_number }}</p>
                                    @endif
                                </td>
                                <td>{{ $asset->category?->name ?? '-' }}</td>
                                <td>
                                    {{ $asset->currentBranch?->name ?? '-' }}
                                    @if ($asset->storageLocation)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $asset->storageLocation->full_path }}</p>
                                    @elseif ($asset->status === \App\Enums\AssetStatus::Available)
                                        <p class="mt-0.5 text-xs text-amber-600">Tempat penyimpanan belum diisi</p>
                                    @endif
                                    @if ($asset->owning_branch_id !== $asset->current_branch_id)
                                        {{-- Sedang tidak berada di cabang pemiliknya: perlu terlihat sekilas,
                                             karena inilah baris yang biasanya dicari saat stock opname. --}}
                                        <p class="mt-0.5 text-xs text-amber-600">Milik {{ $asset->owningBranch?->name }}</p>
                                    @endif
                                </td>
                                <td>{{ $asset->department?->name ?? '-' }}</td>
                                <td><x-status-badge :tone="$asset->status_tone">{{ $asset->status_label }}</x-status-badge></td>
                                <td><x-status-badge :tone="$asset->condition_tone">{{ $asset->condition_label }}</x-status-badge></td>
                                <td>
                                    @if ($asset->warranty_expires_at)
                                        <span @class(['text-sm', 'text-red-600 font-medium' => $asset->warranty_is_expired, 'text-amber-600 font-medium' => $asset->warranty_is_expiring])>
                                            {{ $asset->warranty_expires_at->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-action-menu>
                                        <a href="{{ route('assets.show', $asset) }}" class="action-menu-item"><x-icon name="eye"/> Detail</a>
                                        @can('assets.update')
                                            <a href="{{ route('assets.edit', $asset) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                        @endcan
                                        @can('assets.delete')
                                            @if ($asset->canBeDeleted())
                                                <form method="POST" action="{{ route('assets.destroy', $asset) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </x-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="cell-empty">Belum ada aset yang cocok dengan filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('assets.index') }}" class="flex items-center gap-2">
                    @foreach (array_filter($filters) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="asset_per_page" class="text-sm text-gray-600">Per halaman</label>
                    <select id="asset_per_page" name="per_page" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected(($perPage ?? 15) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </form>
                {{ $assets->links() }}
            </div>
        </section>
    </div>
</x-layouts.app>
