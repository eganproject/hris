<x-layouts.app title="Moderasi Kabar - {{ config('app.name', 'HRIS') }}" heading="Moderasi Kabar">
    <div class="mx-auto max-w-7xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Kabar · aplikasi HP</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Moderasi Kabar</h1>
            <p class="mt-1 text-sm text-gray-500">Postingan karyawan yang dilaporkan rekan. Menyembunyikan tidak menghapus: penulis tetap melihat postingannya beserta alasannya.</p>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('kabar.moderation.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="filter" class="block text-sm font-medium text-gray-700">Tampilkan</label>
                    <select id="filter" name="filter" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="dilaporkan" @selected($filter === 'dilaporkan')>Dilaporkan ({{ $openReports }} laporan terbuka)</option>
                        <option value="disembunyikan" @selected($filter === 'disembunyikan')>Disembunyikan</option>
                        <option value="semua" @selected($filter === 'semua')>Semua postingan</option>
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari isi</label>
                    <input id="search" name="search" value="{{ $search }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white sm:w-auto">Filter</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Postingan</th>
                            <th>Penulis</th>
                            <th>Penerima</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            <tr>
                                <td class="max-w-md">
                                    <a href="{{ route('kabar.moderation.show', $post) }}" class="text-gray-950 hover:text-primary">{{ \Illuminate\Support\Str::limit($post->body, 120) }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $post->created_at->format('d M Y H:i') }} · {{ $post->likes_count }} suka · {{ $post->comments_count }} komentar</p>
                                </td>
                                <td>{{ $post->author?->full_name }}<p class="text-xs text-gray-500">{{ $post->author?->department?->name }}</p></td>
                                <td>{{ $post->audience === 'division' ? 'Divisi '.($post->department?->name ?? '') : 'Semua' }}</td>
                                <td class="space-y-1">
                                    @if ($post->trashed())
                                        <x-status-badge tone="neutral">Dihapus penulis</x-status-badge>
                                    @elseif ($post->isHidden())
                                        <x-status-badge tone="danger">Disembunyikan</x-status-badge>
                                    @else
                                        <x-status-badge tone="success">Tampil</x-status-badge>
                                    @endif
                                    @if ($post->open_reports_count > 0)
                                        <x-status-badge tone="warning">{{ $post->open_reports_count }} laporan</x-status-badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('kabar.moderation.show', $post) }}" class="rounded-md border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Tinjau</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Tidak ada postingan untuk ditinjau.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $posts->links() }}</div>
        </section>
    </div>
</x-layouts.app>
