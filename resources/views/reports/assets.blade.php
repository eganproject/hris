<x-layouts.app title="Register Aset - {{ config('app.name', 'HRIS') }}" heading="Register Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.index') }}" class="hover:text-gray-700">Laporan</a> · Per {{ now()->translatedFormat('d M Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Register Aset</h1>
                <p class="mt-1 text-sm text-gray-500">Seluruh aset beserta status, kondisi, pemegang, dan spesifikasinya — diringkas per {{ strtolower($groupLabel) }}.</p>
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

                    {{-- Ringkas enak dibaca, rinci yang dibawa saat stock opname. Keduanya
                         tetap ada dan pilihannya ikut ke URL, jadi tautan laporan yang
                         dibagikan membuka tampilan yang sama dengan yang dilihat pengirimnya. --}}
                    <select name="view" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($viewOptions as $value => $label)
                            <option value="{{ $value }}" @selected($view === $value)>Tampilan: {{ $label }}</option>
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
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Jumlah aset" :value="number_format($summary['total'])" tone="primary"><x-icon name="box"/></x-stat-card>
            <x-stat-card label="Dipegang karyawan" :value="number_format($summary['assigned'])" tone="sky" hint="Ada pemegangnya"><x-icon name="user-check"/></x-stat-card>
            <x-stat-card label="Dipakai bersama" :value="number_format($summary['in_use'])" tone="violet" hint="Tanpa pemegang"><x-icon name="users"/></x-stat-card>
            <x-stat-card label="Garansi ≤30 hari" :value="number_format($summary['warranty_expiring'])" tone="amber"><x-icon name="refresh"/></x-stat-card>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @php
                $filterName = \App\Support\AssetRegisterReport::GROUP_FILTERS[$groupBy];
                $activeFilter = (string) ($filters[$filterName] ?? '');
                $filterIsExact = \App\Support\AssetRegisterReport::groupFilterIsExact($groupBy);

                // Penyaringnya dipasang lewat URL, bukan parameter baru: dengan begitu
                // kartu ringkas, rekap, dan daftar tetap dihitung dari satu himpunan
                // yang sama, dan hasil kliknya bisa ditautkan ke orang lain apa adanya.
                // 'page' dibuang supaya tidak mendarat di halaman yang sudah tidak ada.
                $rekapUrl = fn (?string $value) => route('reports.assets', array_filter(
                    array_merge(request()->query(), [$filterName => $value, 'page' => null]),
                    fn ($v) => $v !== null && $v !== '',
                ));
            @endphp

            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Rekap per {{ $groupLabel }}</h2>
                <p class="text-xs text-gray-500">
                    Klik baris untuk menyaring seluruh halaman; klik lagi untuk melepas.
                    @unless ($filterIsExact)
                        {{-- Kejujuran yang perlu disebut di tempat kliknya terjadi: kalau
                             tidak, orang akan mengira angkanya salah ketika daftar yang
                             muncul lebih banyak daripada baris yang barusan diklik. --}}
                        Penyaring {{ strtolower($filterName === 'branch' ? 'lokasi' : 'divisi') }} lebih luas daripada sumbu ini, jadi hasilnya bisa memuat lebih banyak aset daripada barisnya.
                    @endunless
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ $groupLabel }}</th><th class="text-right">Jumlah Aset</th><th class="text-right">% Jumlah</th></tr></thead>
                    <tbody>
                        @forelse ($groups as $group)
                            @php
                                $value = $group['key'] === null ? null : (string) $group['key'];
                                $active = $value !== null && $activeFilter === $value;
                            @endphp

                            <tr @class(['bg-primary-soft' => $active, 'transition hover:bg-gray-50' => $value !== null])>
                                <td class="text-sm font-medium text-gray-800">
                                    @if ($value === null)
                                        {{-- "Tanpa Divisi" tidak bisa dinyatakan sebagai nilai penyaring,
                                             jadi barisnya sengaja tidak bisa diklik daripada menautkan ke
                                             daftar yang isinya bukan baris ini. --}}
                                        {{ $group['label'] }}
                                    @else
                                        <a href="{{ $rekapUrl($active ? null : $value) }}" class="flex items-center gap-1.5 hover:text-primary hover:underline">
                                            {{ $group['label'] }}
                                            @if ($active)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 ring-inset">disaring <x-icon name="x" class="size-3"/></span>
                                            @endif
                                        </a>
                                    @endif
                                </td>
                                <td class="text-right text-sm text-gray-700">{{ number_format($group['count']) }}</td>
                                <td class="text-right text-sm text-gray-500">{{ $summary['total'] > 0 ? number_format($group['count'] / $summary['total'] * 100, 1) : '0,0' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cell-empty">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($groups->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50">
                                <td class="text-sm font-semibold text-gray-900">Total</td>
                                <td class="text-right text-sm font-semibold text-gray-900">{{ number_format($summary['total']) }}</td>
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
                <div class="flex flex-wrap items-center gap-3">
                    @if ($view === 'grouped')
                        {{-- Yang dipaginasi nama, bukan unit — jadi yang dihitung di sini juga
                             nama, dan jumlah unitnya disebut terpisah supaya tidak ada yang
                             membaca "25 dari 40" sebagai jumlah barang. --}}
                        <p class="text-xs text-gray-500">Menampilkan {{ number_format($nameGroups->count()) }} dari {{ number_format($nameGroups->total()) }} nama &middot; {{ number_format($summary['total']) }} unit. Unduhan Excel &amp; PDF memuat seluruhnya.</p>
                        <button type="button" data-expand-all aria-pressed="false" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">Buka semua</button>
                    @else
                        <p class="text-xs text-gray-500">Menampilkan {{ $assets->count() }} dari {{ number_format($assets->total()) }} unit. Unduhan Excel &amp; PDF memuat seluruhnya.</p>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ $view === 'grouped' ? 'Nama Aset' : 'Kode & Nama' }}</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Divisi</th>
                            <th>Status</th>
                            <th>Kondisi</th>
                            <th>Pemegang</th>
                            <th>Spesifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($view === 'grouped')
                            @php
                                // Satu nilai kalau seragam, jumlahnya kalau beragam. Menulis
                                // "3 lokasi" lebih jujur daripada memilih salah satunya dan
                                // membuat pembaca mengira unit lainnya ada di tempat yang sama.
                                $ringkas = function ($values, string $noun): string {
                                    $unique = collect($values)->filter()->unique()->values();

                                    return match (true) {
                                        $unique->isEmpty() => '—',
                                        $unique->count() === 1 => (string) $unique->first(),
                                        default => $unique->count().' '.$noun,
                                    };
                                };
                            @endphp

                            @forelse ($nameGroups as $group)
                                @php
                                    $id = 'grup-'.md5($group['key']);
                                    $units = $group['assets'];
                                    $dipegang = $units->filter(fn ($asset) => $asset->currentAssignment?->employee !== null)->count();
                                @endphp

                                @if ($group['units'] === 1 && $units->count() === 1)
                                    {{-- Tidak ada yang perlu dibuka: barisnya langsung unit itu sendiri.
                                         Sebuah tombol yang membuka satu baris berisi keterangan yang
                                         nyaris sama hanya menambah satu klik tanpa menambah apa pun.

                                         $units->count() ikut diperiksa, bukan cuma hitungan dari SQL:
                                         keduanya memang selalu sama, tapi kalau suatu saat tidak, yang
                                         terjadi adalah baris yang isinya diam-diam tidak tampil. --}}
                                    @include('reports._asset-row', ['asset' => $units->first(), 'variant' => 'single', 'groupId' => null])

                                    @continue
                                @endif

                                <tr class="border-t-2 border-gray-200">
                                    <td>
                                        <button type="button" data-group-toggle="{{ $id }}" aria-expanded="false" class="flex items-center gap-2 text-left">
                                            <x-icon name="chevron-down" class="size-4 shrink-0 -rotate-90 text-gray-400 transition-transform" data-group-chevron/>
                                            <span>
                                                <span class="font-semibold text-gray-950">{{ $group['name'] }}</span>
                                                <span class="ml-1.5 inline-flex items-center rounded-md bg-primary-soft px-2 py-0.5 text-xs font-semibold text-gray-700">{{ number_format($group['units']) }} unit</span>
                                            </span>
                                        </button>
                                        @if ($group['spellings'] > 1)
                                            {{-- Penggabungan ini menyamarkan penulisan yang tidak seragam.
                                                 Kalau tidak diberitahukan di sini, tidak akan pernah ada
                                                 yang merapikannya di master aset. --}}
                                            <p class="mt-1 pl-6 text-xs text-amber-600">Ditulis dalam {{ $group['spellings'] }} ejaan berbeda — rapikan di master aset.</p>
                                        @endif
                                    </td>
                                    <td class="text-sm text-gray-600">{{ $ringkas($units->map(fn ($asset) => $asset->category?->name), 'kategori') }}</td>
                                    <td class="text-sm text-gray-600">{{ $ringkas($units->map(fn ($asset) => $asset->currentBranch?->name), 'lokasi') }}</td>
                                    <td class="text-sm text-gray-600">{{ $ringkas($units->map(fn ($asset) => $asset->department?->name), 'divisi') }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($units->groupBy(fn ($asset) => $asset->status_label) as $label => $rows)
                                                <x-status-badge :tone="$rows->first()->status_tone">{{ $label }} {{ $rows->count() }}</x-status-badge>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($units->groupBy(fn ($asset) => $asset->condition_label) as $label => $rows)
                                                <x-status-badge :tone="$rows->first()->condition_tone">{{ $label }} {{ $rows->count() }}</x-status-badge>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-sm text-gray-600">{{ $dipegang > 0 ? $dipegang.' dipegang' : '—' }}</td>
                                    {{-- Spesifikasi yang seragam ditulis apa adanya; yang berbeda-beda
                                         hanya disebut jumlahnya, karena memilih salah satu akan membuat
                                         pembaca mengira unit lain spesifikasinya sama. --}}
                                    <td class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($ringkas($units->map(fn ($asset) => $asset->specification), 'spesifikasi'), 120) }}</td>
                                </tr>

                                @foreach ($units as $asset)
                                    @include('reports._asset-row', ['asset' => $asset, 'variant' => 'child', 'groupId' => $id])
                                @endforeach
                            @empty
                                <tr><td colspan="8" class="cell-empty">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
                            @endforelse
                        @else
                            @forelse ($assets as $asset)
                                @include('reports._asset-row', ['asset' => $asset, 'variant' => 'flat', 'groupId' => null])
                            @empty
                                <tr><td colspan="8" class="cell-empty">Tidak ada aset yang cocok dengan penyaring ini.</td></tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
            @php($daftar = $view === 'grouped' ? $nameGroups : $assets)
            @if ($daftar->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">{{ $daftar->links() }}</div>
            @endif
        </section>

        <p class="text-xs text-gray-400">Spesifikasi dipotong bila terlalu panjang — arahkan kursor ke atasnya untuk membaca utuh, atau buka detail asetnya. Laporan ini tidak lagi menampilkan nilai perolehan; angkanya tetap tersimpan di master aset dan bisa dilihat di halaman detail tiap aset. Kolom Pemegang hanya terisi untuk aset yang sedang diserahkan ke seorang karyawan. Status &ldquo;Dipegang&rdquo; berarti ada satu nama yang bisa dimintai pertanggungjawaban; &ldquo;Dipakai&rdquo; berarti barangnya terpakai bersama dan memang tidak punya pemegang — keduanya dihitung dan diwarnai terpisah, jangan dijumlahkan sebagai satu angka.
            @if ($view === 'grouped')
                Tampilan ringkas menggabungkan aset yang <span class="font-medium">namanya sama</span> setelah besar-kecil huruf dan spasi tepinya diabaikan; penulisan yang benar-benar berbeda seperti &ldquo;iPhone XR&rdquo; dan &ldquo;iPhone XR 64GB&rdquo; tetap terpisah, dan itu memang harus dirapikan di master aset, bukan di laporan.
            @endif
        </p>
    </div>

    @if ($view === 'grouped')
        @push('scripts')
        <script>
            (function () {
                const bodies = (key) => document.querySelectorAll('[data-group-body="' + key + '"]');

                const setGroup = (button, open) => {
                    button.setAttribute('aria-expanded', String(open));
                    button.querySelector('[data-group-chevron]')?.classList.toggle('-rotate-90', !open);
                    bodies(button.dataset.groupToggle).forEach((row) => row.classList.toggle('hidden', !open));
                };

                const toggles = Array.from(document.querySelectorAll('[data-group-toggle]'));

                toggles.forEach((button) => {
                    button.addEventListener('click', () => {
                        setGroup(button, button.getAttribute('aria-expanded') !== 'true');
                    });
                });

                // Satu tombol dua arah: setelah membuka semua, hal berikutnya yang
                // dibutuhkan orang hampir selalu menutupnya kembali.
                const all = document.querySelector('[data-expand-all]');

                all?.addEventListener('click', () => {
                    const open = all.getAttribute('aria-pressed') !== 'true';

                    all.setAttribute('aria-pressed', String(open));
                    all.textContent = open ? 'Tutup semua' : 'Buka semua';
                    toggles.forEach((button) => setGroup(button, open));
                });
            })();
        </script>
        @endpush
    @endif
</x-layouts.app>
