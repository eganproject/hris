<x-layouts.app title="Pola Jadwal - {{ config('app.name', 'HRIS') }}" heading="Pola Jadwal">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master attendance</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Pola Jadwal</h1>
                <p class="mt-1 text-sm text-gray-500">Template shift mingguan atau rotasi yang bisa ditugaskan ke karyawan.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('attendance.schedules.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Lihat Jadwal Kerja</a>
                @can('schedule-patterns.create')<a href="{{ route('attendance.schedule-patterns.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tambah Pola</a>@endcan
            </div>
        </section>

        {{-- Tab dipilih di server lewat ?status, bukan disembunyikan di klien, supaya
             arsip tidak ikut diambil selama tidak dibuka. Pencarian dibawa ikut agar
             berpindah tab tidak menghapus apa yang sedang dicari. --}}
        <section class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6 text-sm font-medium" aria-label="Tab pola jadwal">
                @foreach ([['Aktif', $activeCount, null], ['Arsip', $archivedCount, 'archived']] as [$label, $count, $status])
                    @php $current = $archived === ($status === 'archived'); @endphp
                    <a href="{{ route('attendance.schedule-patterns.index', array_filter(['status' => $status, 'search' => $filters['search'] ?? null, 'per_page' => $perPage])) }}"
                        @class([
                            'flex items-center gap-2 border-b-2 px-1 pb-3 transition',
                            'border-primary text-primary' => $current,
                            'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! $current,
                        ])
                        @if ($current) aria-current="page" @endif>
                        {{ $label }}
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-primary/10 text-primary' => $current,
                            'bg-gray-100 text-gray-600' => ! $current,
                        ])>{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>
        </section>

        @if ($archived)
            <div class="flex items-start gap-2.5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <x-icon name="info" class="mt-0.5 size-4"/>
                <p>Pola di arsip tidak lagi muncul di pilihan penugasan, tapi jadwal karyawan yang sudah memakainya tetap berjalan seperti biasa. Klik jumlah pemakainya untuk melihat siapa saja.</p>
            </div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.schedule-patterns.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto]">
                @if ($archived)<input type="hidden" name="status" value="archived">@endif
                <input name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari kode atau nama pola">
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('attendance.schedule-patterns.index', array_filter(['status' => $archived ? 'archived' : null])) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pola</th><th>Tipe</th><th>Ringkasan</th><th>Dipakai</th><th>{{ $archived ? 'Diarsipkan' : 'Status' }}</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($patterns as $pattern)
                            @php
                                $working = $pattern->days->whereNotNull('shift_id')->count();
                                $off = $pattern->days->whereNull('shift_id')->count();
                                $assigned = (int) $pattern->assigned_employees_count;
                                $office = (int) $pattern->office_employees_count;
                            @endphp
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $pattern->name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $pattern->code }}</p></td>
                                <td>
                                    <x-status-badge tone="info">{{ $pattern->type->label() }}</x-status-badge>
                                    @if ($pattern->type === \App\Enums\SchedulePatternType::Rotating)
                                        <p class="mt-1 text-xs text-gray-500">Siklus {{ $pattern->cycle_length }} hari</p>
                                    @endif
                                </td>
                                <td class="text-xs text-gray-600">{{ $working }} hari kerja · {{ $off }} libur</td>
                                {{-- Dua angka, bukan satu jumlah: penugasan dan jam kantor adalah dua
                                     jalur berbeda menuju pola ini, dan satu orang bisa masuk keduanya. --}}
                                <td class="text-sm">
                                    <a href="{{ route('attendance.schedule-patterns.employees', $pattern) }}" class="font-medium text-primary hover:underline">{{ $assigned }} penugasan</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $office }} karyawan jam kantor</p>
                                </td>
                                <td>
                                    @if ($archived)
                                        <p class="text-sm text-gray-700">{{ $pattern->deleted_at?->translatedFormat('d M Y') }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">Status sebelumnya: {{ $pattern->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                                    @else
                                        <x-status-badge :tone="$pattern->is_active ? 'success' : 'neutral'">{{ $pattern->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($archived)
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('attendance.schedule-patterns.employees', $pattern) }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"><x-icon name="users"/> Karyawan</a>
                                            @can('schedule-patterns.delete')
                                                <form method="POST" action="{{ route('attendance.schedule-patterns.restore', $pattern) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"><x-icon name="refresh"/> Pulihkan</button>
                                                </form>
                                            @endcan
                                        </div>
                                    @else
                                        <x-action-menu>
                                            <a href="{{ route('attendance.schedule-patterns.employees', $pattern) }}" class="action-menu-item"><x-icon name="users"/> Lihat karyawan</a>
                                            @can('schedules.create')<a href="{{ route('attendance.schedules.assign') }}" class="action-menu-item"><x-icon name="plus"/> Tugaskan</a>@endcan
                                            @can('schedule-patterns.update')<a href="{{ route('attendance.schedule-patterns.edit', $pattern) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>@endcan
                                            @can('schedule-patterns.delete')<form method="POST" action="{{ route('attendance.schedule-patterns.destroy', $pattern) }}" onsubmit="return confirm('Pindahkan pola ini ke arsip? Jadwal karyawan yang sudah memakainya tetap berjalan.')">@csrf @method('DELETE')<button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Arsipkan</button></form>@endcan
                                        </x-action-menu>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">{{ $archived ? 'Tidak ada pola di arsip.' : 'Belum ada pola jadwal.' }} @unless ($archived)<a href="{{ route('attendance.schedule-patterns.create') }}" class="text-primary">Tambah pola pertama</a>.@endunless</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $patterns->links() }}</div>
        </section>
    </div>
</x-layouts.app>
