<x-layouts.app title="Serah Terima Aset - {{ config('app.name', 'HRIS') }}" heading="Serah Terima Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Manajemen aset</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Serah Terima Aset</h1>
            <p class="mt-1 text-sm text-gray-500">Siapa memegang apa, mana yang belum dikonfirmasi, dan mana yang telat kembali.</p>
        </section>

        <x-scope-notice :has-no-scope="$hasNoScope" />

        {{-- Tab keadaan mempertahankan penyaring yang sedang aktif. Kalau tidak, tiap
             kali orang berpindah dari "Sedang dipegang" ke "Telat kembali" ia kehilangan
             lokasi dan divisi yang barusan dipilih, dan mengira penyaringnya rusak.
             'page' dibuang supaya tidak mendarat di halaman yang sudah tidak ada. --}}
        @php
            $tabUrl = fn (string $value) => route('assets.assignments.index', array_filter(
                array_merge(request()->query(), ['state' => $value, 'page' => null]),
                fn ($v) => $v !== null && $v !== '',
            ));
        @endphp

        <section class="flex flex-wrap gap-2">
            @foreach ($states as $value => $label)
                <a href="{{ $tabUrl($value) }}"
                   @class([
                       'rounded-md px-4 py-2 text-sm font-medium transition',
                       'bg-primary text-white shadow-xs' => $filter === $value,
                       'border border-gray-200 text-gray-700 hover:bg-gray-50' => $filter !== $value,
                   ])>{{ $label }}</a>
            @endforeach
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('assets.assignments.index') }}" class="space-y-3">
                {{-- Keadaan yang sedang dipilih ikut terkirim, supaya menekan "Filter"
                     tidak diam-diam melempar orang kembali ke tab bawaan. --}}
                <input type="hidden" name="state" value="{{ $filter }}">

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label for="assign_search" class="block text-sm font-medium text-gray-700">Cari</label>
                        <input id="assign_search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Kode aset, nama aset, nomor seri, atau nama karyawan" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>

                    <div>
                        <label for="assign_employee" class="block text-sm font-medium text-gray-700">Pemegang</label>
                        <select id="assign_employee" name="employee" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected($filters['employee'] === $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assign_category" class="block text-sm font-medium text-gray-700">Kategori</label>
                        <select id="assign_category" name="category" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assign_branch" class="block text-sm font-medium text-gray-700">Lokasi</label>
                        <select id="assign_branch" name="branch" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua lokasi</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($filters['branch'] === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assign_department" class="block text-sm font-medium text-gray-700">Divisi</label>
                        <select id="assign_department" name="department" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua divisi</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($filters['department'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assign_from" class="block text-sm font-medium text-gray-700">Diserahkan dari</label>
                        <input id="assign_from" type="date" name="from" value="{{ $filters['from'] }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>

                    <div>
                        <label for="assign_to" class="block text-sm font-medium text-gray-700">Sampai</label>
                        <input id="assign_to" type="date" name="to" value="{{ $filters['to'] }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-400">Penyaring lokasi, kategori, dan divisi membaca data asetnya — aturannya sama persis dengan halaman Daftar Aset. Rentang tanggal menyaring tanggal penyerahan.</p>
                    <div class="flex gap-2">
                        <a href="{{ route('assets.assignments.index', ['state' => $filter]) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Filter</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Aset</th>
                            <th>Pemegang</th>
                            <th>Diserahkan</th>
                            <th>Target Kembali</th>
                            <th>Konfirmasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td>
                                    <a href="{{ route('assets.show', $assignment->asset_id) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $assignment->asset?->name }}</a>
                                    <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $assignment->asset?->asset_code }}</p>
                                </td>
                                <td>{{ $assignment->employee?->full_name ?? '-' }}</td>
                                <td>
                                    <p class="text-sm text-gray-900">{{ $assignment->assigned_at?->translatedFormat('d M Y') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">oleh {{ $assignment->assignedBy?->name ?? '-' }}</p>
                                </td>
                                <td>
                                    @if ($assignment->expected_return_at)
                                        <span @class(['text-sm', 'font-medium text-red-600' => $assignment->isOverdue(), 'text-gray-900' => ! $assignment->isOverdue()])>
                                            {{ $assignment->expected_return_at->translatedFormat('d M Y') }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">Tanpa batas</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->isAcknowledged())
                                        <x-status-badge tone="success">Dikonfirmasi</x-status-badge>
                                    @elseif ($assignment->isOpen())
                                        <x-status-badge tone="warning">Menunggu</x-status-badge>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->isOpen())
                                        <x-status-badge :tone="$assignment->isOverdue() ? 'danger' : 'info'">{{ $assignment->isOverdue() ? 'Telat kembali' : 'Dipegang' }}</x-status-badge>
                                    @else
                                        <x-status-badge tone="neutral">Kembali {{ $assignment->returned_at?->translatedFormat('d M Y') }}</x-status-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada serah-terima yang cocok dengan penyaring ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('assets.assignments.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="state" value="{{ $filter }}">
                    @foreach (array_filter($filters, fn ($value) => $value !== null && $value !== '') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="assign_per_page" class="text-sm text-gray-600">Per halaman</label>
                    <select id="assign_per_page" name="per_page" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <span class="text-sm text-gray-500">&middot; {{ number_format($assignments->total()) }} serah terima</span>
                </form>
                {{ $assignments->links() }}
            </div>
        </section>
    </div>
</x-layouts.app>
