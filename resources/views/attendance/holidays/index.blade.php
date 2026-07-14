<x-layouts.app title="Hari Libur - {{ config('app.name', 'HRIS') }}" heading="Hari Libur">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master attendance</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Hari Libur</h1>
            </div>
            @can('holidays.create')
                <a href="{{ route('attendance.holidays.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                    <x-icon name="plus" class="size-4"/> Tambah Libur
                </a>
            @endcan
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.holidays.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Nama hari libur">
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700">Tahun</label>
                    <select id="year" name="year" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($years as $option)
                            <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white sm:w-auto">Filter</button>
                    <a href="{{ route('attendance.holidays.index') }}" class="w-full rounded-md border border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama</th>
                            <th>Lingkup</th>
                            <th>Catatan</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-950">{{ $holiday->date->format('d M Y') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $holiday->date->translatedFormat('l') }}</p>
                                </td>
                                <td>{{ $holiday->name }}</td>
                                <td>
                                    @if ($holiday->is_national)
                                        <x-status-badge tone="info">Nasional</x-status-badge>
                                    @else
                                        <x-status-badge tone="neutral">{{ $holiday->branch?->name ?? 'Lokasi' }}</x-status-badge>
                                    @endif
                                </td>
                                <td class="text-gray-500">{{ $holiday->notes ?? '-' }}</td>
                                <td class="text-right">
                                    @canany(['holidays.update', 'holidays.delete'])
                                        <x-action-menu>
                                            @can('holidays.update')
                                                <a href="{{ route('attendance.holidays.edit', $holiday) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                            @endcan
                                            @can('holidays.delete')
                                                <form method="POST" action="{{ route('attendance.holidays.destroy', $holiday) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                                </form>
                                            @endcan
                                        </x-action-menu>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Belum ada hari libur untuk tahun {{ $year }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $holidays->links() }}</div>
        </section>
    </div>
</x-layouts.app>
