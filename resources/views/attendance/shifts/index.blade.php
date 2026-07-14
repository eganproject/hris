<x-layouts.app title="Shift - {{ config('app.name', 'HRIS') }}" heading="Shift">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-gray-500">Master attendance</p><h1 class="mt-1 text-2xl font-semibold text-gray-950">Shift Kerja</h1></div>
            @can('shifts.create')<a href="{{ route('attendance.shifts.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tambah Shift</a>@endcan
        </section>
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.shifts.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_160px_auto_auto]">
                <div>
                    <label for="shift_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="shift_search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari kode atau nama shift">
                </div>
                <div>
                    <label for="shift_per_page" class="block text-sm font-medium text-gray-700">Per halaman</label>
                    <select id="shift_per_page" name="per_page" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected(($perPage ?? 15) === $option)>{{ $option }} / halaman</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('attendance.shifts.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </section>
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Shift</th><th>Jam Kerja</th><th>Istirahat</th><th>Toleransi</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $shift->name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $shift->code }}</p></td>
                                <td>
                                    {{ $shift->time_range_label }}
                                    @if ($shift->crosses_midnight)<span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">Lintas malam</span>@endif
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $shift->work_minutes !== null ? floor($shift->work_minutes / 60).'j '.($shift->work_minutes % 60).'m kerja' : '-' }}</p>
                                </td>
                                <td>{{ number_format($shift->break_minutes) }} menit</td>
                                <td class="text-xs text-gray-600">
                                    Telat {{ $shift->late_tolerance_minutes }}m · Pulang {{ $shift->early_leave_tolerance_minutes }}m
                                    <p class="mt-0.5 text-gray-400">Lembur: {{ $shift->overtime_rule_label }}</p>
                                </td>
                                <td>
                                    <x-status-badge :tone="$shift->is_active ? 'success' : 'neutral'">{{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                                </td>
                                <td class="text-right">
                                    @canany(['shifts.update', 'shifts.delete'])
                                        <x-action-menu>
                                            @can('shifts.update')<a href="{{ route('attendance.shifts.edit', $shift) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>@endcan
                                            @can('shifts.delete')<form method="POST" action="{{ route('attendance.shifts.destroy', $shift) }}">@csrf @method('DELETE')<button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button></form>@endcan
                                        </x-action-menu>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada shift.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $shifts->links() }}</div>
        </section>
    </div>
</x-layouts.app>
