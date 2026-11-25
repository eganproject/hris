<x-layouts.app title="Tinjau Postingan - {{ config('app.name', 'HRIS') }}" heading="Moderasi Kabar">
    <div class="mx-auto max-w-4xl space-y-6">
        <section>
            <a href="{{ route('kabar.moderation.index') }}" class="text-sm font-medium text-gray-500 hover:text-primary">← Moderasi Kabar</a>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Postingan {{ $post->author?->full_name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $post->author?->department?->name }} · {{ $post->created_at->format('d M Y H:i') }} · {{ $post->audience === 'division' ? 'Divisi '.($post->department?->name ?? '') : 'Semua karyawan' }} · {{ $post->likes_count }} suka</p>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="whitespace-pre-line text-sm leading-6 text-gray-800">{{ $post->body }}</div>
            @if ($post->image_path)
                <a href="{{ route('kabar.moderation.image', $post) }}" target="_blank" class="mt-4 inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="eye" class="size-4"/> Lihat foto</a>
            @endif

            @if ($post->isHidden())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Disembunyikan {{ $post->hidden_at->format('d M Y H:i') }} oleh {{ $post->hider?->name ?? 'HR' }}.@if ($post->hidden_reason) Alasan: {{ $post->hidden_reason }}@endif
                </div>
            @endif

            @can('kabar-moderation.update')
                @unless ($post->trashed())
                    <div class="mt-5 flex flex-col gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-end">
                        @if ($post->isHidden())
                            <form method="POST" action="{{ route('kabar.moderation.unhide', $post) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Tampilkan kembali</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('kabar.moderation.hide', $post) }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                                @csrf @method('PATCH')
                                <div class="flex-1">
                                    <label for="reason" class="block text-sm font-medium text-gray-700">Alasan (dikirim ke penulis)</label>
                                    <input id="reason" name="reason" maxlength="255" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. Tidak sesuai etika kerja">
                                </div>
                                <button type="submit" class="rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">Sembunyikan</button>
                            </form>
                            @if ($reports->whereNull('resolved_at')->isNotEmpty())
                                <form method="POST" action="{{ route('kabar.moderation.dismiss', $post) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Tutup laporan (tidak melanggar)</button>
                                </form>
                            @endif
                        @endif
                    </div>
                @endunless
            @endcan
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3 text-sm font-semibold text-gray-950">Laporan · {{ $reports->count() }}</div>
            <table class="data-table">
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td>{{ $report->reporter?->full_name }}<p class="text-xs text-gray-500">{{ $report->created_at->format('d M Y H:i') }}{{ $report->comment ? ' · tentang komentar' : '' }}</p></td>
                            <td>{{ $report->reason ?? '—' }}</td>
                            <td class="text-right">
                                @if ($report->resolved_at)
                                    <x-status-badge tone="neutral">Ditutup</x-status-badge>
                                @else
                                    <x-status-badge tone="warning">Terbuka</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="cell-empty">Tidak ada laporan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3 text-sm font-semibold text-gray-950">Komentar · {{ $comments->count() }}</div>
            <table class="data-table">
                <tbody>
                    @forelse ($comments as $comment)
                        <tr>
                            <td class="max-w-lg">
                                <p class="text-xs text-gray-500">{{ $comment->author?->full_name }} · {{ $comment->created_at->format('d M H:i') }}{{ $comment->parent ? ' · balasan untuk '.$comment->parent->author?->full_name : '' }}</p>
                                <p class="mt-0.5 text-gray-800">{{ $comment->body }}</p>
                            </td>
                            <td class="text-right">
                                @if ($comment->hidden_at)
                                    <x-status-badge tone="danger">Disembunyikan</x-status-badge>
                                @endif
                                @can('kabar-moderation.update')
                                    <form method="POST" action="{{ route('kabar.moderation.comment', $comment) }}" class="mt-1">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ $comment->hidden_at ? 'Tampilkan' : 'Sembunyikan' }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="cell-empty">Belum ada komentar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-layouts.app>
