<x-layouts.app title="Register Aset - {{ config('app.name', 'HRIS') }}" heading="Register Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.index') }}" class="hover:text-gray-700">Laporan</a> · Per {{ now()->translatedFormat('d M Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Register Aset</h1>
                <p class="mt-1 text-sm text-gray-500">Seluruh aset beserta status, kondisi, pemegang, dan nilai perolehannya — diringkas per {{ strtolower($groupLabel) }}.</p>
            </div>
            @can('reports.assets.export')
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.assets.pdf', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                        <x-icon name="download" class="size-4"/> PDF
                    </a>
                    <a href="{{ route('reports.assets.export', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                        <x-icon name="download" class="size-4"/> Excel
                    </a>
                </div>
            @endcan
        </section>

        <x-scope-notice :has-no-scope="$hasNoScope"/>

        @if ($limitedToSubordinates)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Akun Anda dibatasi ke bawahan, sehingga laporan ini hanya menghitung aset yang <span class="font-medium">sedang dipegang</span> karyawan di bawah Anda — bukan seluruh aset di lokasi kerja. Untuk register per lokasi kerja, minta admin mengubah cakupan akun Anda di menu <span class="font-medium">Kontrol Akses</span>.
            </div>
        @endif

        {{-- Penyaring: bentuknya sama persis dengan halaman Daftar Aset, supaya angka
             di laporan bisa ditelusuri balik ke layar operasionalnya. --}}
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('reports.assets') }}" class="space-y-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Cari kode, nama, atau nomor seri" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">

                    <select name="category" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <select name="branch" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $filters['branch'] === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>

                    <select name="department" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) $filters['department'] === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="condition" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua kondisi</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['condition'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="warranty" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Garansi: semua</option>
                        <option value="expiring" @selected($filters['warranty'] === 'expiring')>Berakhir ≤30 hari</option>
                        <option value="expired" @selected($filters['warranty'] === 'expired')>Sudah lewat</option>
                    </select>

                    <select name="group" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($groupOptions as $value => $label)
                            <option value="{{ $value }}" @selected($groupBy === $value)>Kelompokkan per {{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs text-gray-400">Filter lokasi mencakup lokasi pemilik maupun lokasi sekarang.</p>
                    <div class="flex gap-2">
                        <a href="{{ route('reports.assets') }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Terapkan</button>
                    </div>
                </div>
            </form>
        </section>

        {{-- Dipegang dan Dipakai dihitung sebagai dua angka, bukan satu "sedang
             terpakai": hanya yang Dipegang punya nama karyawan yang bisa dimintai
             pertanggungjawaban, dan laporan inilah yang dipakai untuk menagih. --}}
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-stat-card label="Jumlah aset" :value="number_format($summary['total'])" tone="primary"><x-icon name="box"/></x-stat-card>
            <x-stat-card label="Nilai perolehan" :value="'Rp '.number_format($summary['value'], 0, ',', '.')" tone="gray"><x-icon name="banknote"/></x-stat-card>
            <x-stat-card label="Dipegang karyawan" :value="number_format($summary['assigned'])" tone="sky" hint="Ada pemegangnya"><x-icon name="user-check"/></x-stat-card>
            <x-stat-card label="Dipakai bersama" :value="number_format($summary['in_use'])" tone="violet" hint="Tanpa pemegang"><x-icon name="users"/></x-stat-card>
            <x-stat-card label="Garansi ≤30 hari" :value="number_format($summary['warranty_expiring'])" tone="amber"><x-icon name="refresh"/></x-stat-card>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Rekap per {{ $groupLabel }}</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ $groupLabel }}</th><th class="text-right">Jumlah Aset</th><th class="text-right">Nilai Perolehan</th><th class="text-right">% Jumlah</th></tr></thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td class="text-sm font-medium text-gray-800">{{ $group['label'] }}</td>
                                <td class="text-right text-sm text-gray-700">{{ number_format($group['count']) }}</td>
                                <td class="text-right text-sm text-gray-700">Rp {{ number_format($group['value'], 0, ',', '.') }}</td>
                                <td class="text-right text-sm text-gray-500">{{ $summary['total'] > 0 ? number_format($group['count'] / $summary['total'] * 100, 1) : '0,0' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="cell-empty">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($groups->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50">
                                <td class="text-sm font-semibold text-gray-900">Total</td>
                                <td class="text-right text-sm font-semibold text-gray-900">{{ number_format($summary['total']) }}</td>
                                <td class="text-right text-sm font-semibold text-gray-900">Rp {{ number_format($summary['value'], 0, ',', '.') }}</td>
                                <td class="text-right text-sm font-semibold text-gray-900">100,0%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Daftar Aset</h2>
                <p class="text-xs text-gray-500">Menampilkan {{ $assets->count() }} dari {{ number_format($assets->total()) }} aset. Unduhan Excel & PDF memuat seluruhnya.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kode & Nama</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Divisi</th>
                            <th>Status</th>
                            <th>Kondisi</th>
                            <th>Pemegang</th>
                            <th class="text-right">Nilai Perolehan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td>
                                    <a href="{{ route('assets.show', $asset) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $asset->asset_code }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $asset->name }}</p>
                                </td>
                                <td class="text-sm text-gray-600">{{ $asset->category?->name ?? '—' }}</td>
                                <td class="text-sm text-gray-600">
                                    {{ $asset->currentBranch?->name ?? '—' }}
                                    @if ($asset->owning_branch_id !== $asset->current_branch_id)
                                        <span class="block text-xs text-gray-400">milik {{ $asset->owningBranch?->name ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="text-sm text-gray-600">{{ $asset->department?->name ?? '—' }}</td>
                                <td><x-status-badge :tone="$asset->status_tone">{{ $asset->status_label }}</x-status-badge></td>
                                <td><x-status-badge :tone="$asset->condition_tone">{{ $asset->condition_label }}</x-status-badge></td>
                                <td class="text-sm text-gray-600">
                                    @if ($asset->currentAssignment?->employee)
                                        {{ $asset->currentAssignment->employee->full_name }}
                                        <span class="block text-xs text-gray-400">sejak {{ $asset->currentAssignment->assigned_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                    @elseif ($asset->status?->isSharedUse())
                                        {{-- Bukan "—" seperti aset menganggur: kosongnya di sini disengaja,
                                             dan pembaca perlu tahu bedanya sebelum menyimpulkan barangnya
                                             luput dicatat serah terimanya. --}}
                                        <span class="text-violet-700">Pakai bersama</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="text-right text-sm text-gray-700">{{ $asset->acquisition_cost === null ? '—' : 'Rp '.number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="cell-empty">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($assets->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">{{ $assets->links() }}</div>
            @endif
        </section>

        <p class="text-xs text-gray-400">Nilai perolehan adalah harga beli yang tercatat saat aset didaftarkan, bukan nilai buku — penyusutan belum dihitung sistem. Kolom Pemegang hanya terisi untuk aset yang sedang diserahkan ke seorang karyawan. Status &ldquo;Dipegang&rdquo; berarti ada satu nama yang bisa dimintai pertanggungjawaban; &ldquo;Dipakai&rdquo; berarti barangnya terpakai bersama dan memang tidak punya pemegang — keduanya dihitung dan diwarnai terpisah, jangan dijumlahkan sebagai satu angka.</p>
    </div>
</x-layouts.app>
