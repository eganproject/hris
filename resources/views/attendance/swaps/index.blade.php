<x-layouts.app title="Tukar Jadwal - {{ config('app.name', 'HRIS') }}" heading="Persetujuan Tukar Jadwal">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Peninjauan HR</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Persetujuan Tukar Jadwal</h1>
                <p class="mt-1 text-sm text-gray-500">Permintaan yang sudah disetujui rekan. Menyetujui akan menerapkan perubahan ke jadwal.</p>
            </div>
            @if ($pendingCount > 0)<x-status-badge tone="warning">{{ $pendingCount }} menunggu HR</x-status-badge>@endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['pending_hr' => 'Menunggu HR', 'pending_partner' => 'Menunggu Rekan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $value => $label)
                    <a href="{{ route('attendance.swaps.index', ['status' => $value]) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $status === $value, 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $status !== $value])>{{ $label }}</a>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Jenis</th><th>Pengaju</th><th>Rekan</th><th>Tanggal</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($requests as $req)
                            <tr>
                                <td class="text-sm text-gray-800">{{ $req->type_label }}@if ($req->reason)<p class="mt-0.5 max-w-[16rem] truncate text-xs text-gray-500" title="{{ $req->reason }}">{{ $req->reason }}</p>@endif</td>
                                <td class="text-sm text-gray-700">{{ $req->requester?->full_name }}</td>
                                <td class="text-sm text-gray-700">{{ $req->partner?->full_name }}</td>
                                <td class="text-sm text-gray-600">{{ $req->requester_date->translatedFormat('d M') }}@if ($req->partner_date) ⇄ {{ $req->partner_date->translatedFormat('d M') }}@endif</td>
                                <td>
                                    <x-status-badge :tone="$req->status_tone">{{ $req->status_label }}</x-status-badge>
                                    @if ($req->reviewer)<p class="mt-1 text-xs text-gray-400">oleh {{ $req->reviewer->name }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @can('swaps.update')
                                        @if ($req->isPendingHr())
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('attendance.swaps.approve', $req) }}" onsubmit="return confirm('Setujui & terapkan tukar jadwal?')">@csrf @method('PATCH')<button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button></form>
                                                <form method="POST" action="{{ route('attendance.swaps.reject', $req) }}">@csrf @method('PATCH')<button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $req->decided_at?->format('d M H:i') }}</span>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Tidak ada permintaan tukar jadwal.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $requests->links() }}</div>
        </section>
    </div>
</x-layouts.app>
