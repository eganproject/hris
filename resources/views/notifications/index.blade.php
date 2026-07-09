<x-layouts.app title="Notifikasi - {{ config('app.name', 'HRIS') }}" heading="Notifikasi">
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-500">Pusat notifikasi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Notifikasi</h1>
            </div>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}" data-no-confirm="true">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Tandai semua dibaca</button>
                </form>
            @endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="divide-y divide-gray-100">
                @forelse ($notifications as $notif)
                    <a href="{{ route('notifications.read', $notif->id) }}" @class(['flex items-start gap-3 px-5 py-3.5 transition hover:bg-gray-50', 'bg-primary-soft' => is_null($notif->read_at)])>
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-red-500' => is_null($notif->read_at), 'bg-gray-200' => ! is_null($notif->read_at)])></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ $notif->data['title'] ?? 'Notifikasi' }}</p>
                            <p class="mt-0.5 text-sm text-gray-600">{{ $notif->data['message'] ?? '' }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $notif->created_at->translatedFormat('D, d M Y H:i') }} · {{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">Belum ada notifikasi.</p>
                @endforelse
            </div>
        </section>

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
