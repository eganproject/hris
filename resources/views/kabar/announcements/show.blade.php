<x-layouts.app title="{{ $announcement->title }} - {{ config('app.name', 'HRIS') }}" heading="Pengumuman HR">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('kabar.announcements.index') }}" class="text-sm font-medium text-gray-500 hover:text-primary">← Pengumuman HR</a>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $announcement->title }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $announcement->creator?->name ?? 'HR' }} · {{ $announcement->audience_label }} · {{ $announcement->published_at?->format('d M Y H:i') ?? 'Draf' }}</p>
            </div>
            @can('announcements.update')
                <a href="{{ route('kabar.announcements.edit', $announcement) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><x-icon name="pencil" class="size-4"/> Edit</a>
            @endcan
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="whitespace-pre-line text-sm leading-6 text-gray-800">{{ $announcement->body }}</div>
            @if ($announcement->hasAttachment())
                <a href="{{ route('kabar.announcements.attachment', $announcement) }}" target="_blank" class="mt-4 inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="download" class="size-4"/> {{ $announcement->attachment_name }}</a>
            @endif
        </section>

        <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-3 text-sm font-semibold text-gray-950">Sudah membaca · {{ $reads->count() }} dari {{ $audienceCount }}</div>
                <table class="data-table">
                    <tbody>
                        @forelse ($reads as $read)
                            <tr>
                                <td>{{ $read->employee?->full_name }}<p class="text-xs text-gray-500">{{ $read->employee?->department?->name }}</p></td>
                                <td class="text-right text-gray-500">{{ $read->read_at->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="cell-empty">Belum ada yang menandai sudah membaca.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-3 text-sm font-semibold text-gray-950">Belum membaca · {{ $unread->count() }}</div>
                <table class="data-table">
                    <tbody>
                        @forelse ($unread as $employee)
                            <tr><td>{{ $employee->full_name }}<p class="text-xs text-gray-500">{{ $employee->department?->name }}</p></td></tr>
                        @empty
                            <tr><td class="cell-empty">Semua penerima sudah membaca.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
