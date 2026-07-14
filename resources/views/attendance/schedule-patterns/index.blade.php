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

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.schedule-patterns.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto]">
                <input name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari kode atau nama pola">
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('attendance.schedule-patterns.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pola</th><th>Tipe</th><th>Ringkasan</th><th>Dipakai</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($patterns as $pattern)
                            @php
                                $working = $pattern->days->whereNotNull('shift_id')->count();
                                $off = $pattern->days->whereNull('shift_id')->count();
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
                                <td class="text-sm text-gray-700">{{ $pattern->assignments_count }} karyawan</td>
                                <td><x-status-badge :tone="$pattern->is_active ? 'success' : 'neutral'">{{ $pattern->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge></td>
                                <td class="text-right">
                                    @canany(['schedule-patterns.update', 'schedule-patterns.delete', 'schedule-patterns.create'])
                                        <x-action-menu>
                                            @can('schedules.create')<a href="{{ route('attendance.schedules.assign') }}" class="action-menu-item"><x-icon name="plus"/> Tugaskan</a>@endcan
                                            @can('schedule-patterns.update')<a href="{{ route('attendance.schedule-patterns.edit', $pattern) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>@endcan
                                            @can('schedule-patterns.delete')<form method="POST" action="{{ route('attendance.schedule-patterns.destroy', $pattern) }}" onsubmit="return confirm('Hapus pola ini?')">@csrf @method('DELETE')<button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button></form>@endcan
                                        </x-action-menu>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada pola jadwal. <a href="{{ route('attendance.schedule-patterns.create') }}" class="text-primary">Tambah pola pertama</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $patterns->links() }}</div>
        </section>
    </div>
</x-layouts.app>
