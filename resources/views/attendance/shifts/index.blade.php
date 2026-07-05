<x-layouts.app title="Shift - {{ config('app.name', 'HRIS') }}" heading="Shift">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-gray-500">Master attendance</p><h1 class="mt-1 text-2xl font-semibold text-gray-950">Shift Kerja</h1></div>
            @can('attendance.create')<a href="{{ route('attendance.shifts.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tambah Shift</a>@endcan
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
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left font-medium text-gray-500">Shift</th><th class="px-5 py-3 text-left font-medium text-gray-500">Jam Kerja</th><th class="px-5 py-3 text-left font-medium text-gray-500">Istirahat</th><th class="px-5 py-3 text-left font-medium text-gray-500">Status</th><th class="px-5 py-3 text-right font-medium text-gray-500">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($shifts as $shift)
                            <tr>
                                <td class="px-5 py-4"><p class="font-medium text-gray-950">{{ $shift->name }}</p><p class="mt-1 text-xs text-gray-500">{{ $shift->code }}</p></td>
                                <td class="px-5 py-4 text-gray-600">{{ str($shift->start_time)->substr(0, 5) }} - {{ str($shift->end_time)->substr(0, 5) }}</td>
                                <td class="px-5 py-4 text-gray-600">{{ number_format($shift->break_minutes) }} menit</td>
                                <td class="px-5 py-4"><span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="px-5 py-4"><div class="flex justify-end gap-2">@can('attendance.update')<a href="{{ route('attendance.shifts.edit', $shift) }}" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Edit</a>@endcan @can('attendance.delete')<form method="POST" action="{{ route('attendance.shifts.destroy', $shift) }}">@csrf @method('DELETE')<button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Hapus</button></form>@endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada shift.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $shifts->links() }}</div>
        </section>
    </div>
</x-layouts.app>
