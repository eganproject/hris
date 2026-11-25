<x-layouts.app title="Pengumuman HR - {{ config('app.name', 'HRIS') }}" heading="Pengumuman HR">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Kabar · aplikasi HP</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Pengumuman HR</h1>
                <p class="mt-1 text-sm text-gray-500">Tampil di tab Kabar aplikasi HP. Karyawan menandai "Saya sudah membaca".</p>
            </div>
            @can('announcements.create')
                <a href="{{ route('kabar.announcements.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                    <x-icon name="plus" class="size-4"/> Tulis Pengumuman
                </a>
            @endcan
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('kabar.announcements.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-3">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Judul pengumuman">
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white sm:w-auto">Filter</button>
                    <a href="{{ route('kabar.announcements.index') }}" class="w-full rounded-md border border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Penerima</th>
                            <th>Terbit</th>
                            <th>Dibaca</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($announcements as $announcement)
                            <tr>
                                <td>
                                    <a href="{{ route('kabar.announcements.show', $announcement) }}" class="font-medium text-gray-950 hover:text-primary">{{ $announcement->title }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $announcement->creator?->name ?? 'HR' }}@if ($announcement->is_pinned) · disematkan @endif</p>
                                </td>
                                <td>{{ $announcement->audience_label }}</td>
                                <td>
                                    @if (! $announcement->published_at)
                                        <x-status-badge tone="neutral">Draf</x-status-badge>
                                    @elseif ($announcement->published_at->isFuture())
                                        <x-status-badge tone="warning">Terjadwal {{ $announcement->published_at->format('d M H:i') }}</x-status-badge>
                                    @else
                                        {{ $announcement->published_at->format('d M Y H:i') }}
                                    @endif
                                </td>
                                <td>{{ $announcement->reads_count }} dari {{ $announcement->audienceQuery()->count() }}</td>
                                <td class="text-right">
                                    <x-action-menu>
                                        <a href="{{ route('kabar.announcements.show', $announcement) }}" class="action-menu-item"><x-icon name="eye"/> Lihat pembaca</a>
                                        @can('announcements.update')
                                            <a href="{{ route('kabar.announcements.edit', $announcement) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                        @endcan
                                        @can('announcements.delete')
                                            <form method="POST" action="{{ route('kabar.announcements.destroy', $announcement) }}" onsubmit="return confirm('Hapus pengumuman ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                            </form>
                                        @endcan
                                    </x-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Belum ada pengumuman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $announcements->links() }}</div>
        </section>
    </div>
</x-layouts.app>
