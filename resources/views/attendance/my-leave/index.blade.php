<x-layouts.app title="Cuti Saya - {{ config('app.name', 'HRIS') }}" heading="Cuti Saya">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Self-service</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Cuti Saya</h1>
            </div>
            <a href="{{ route('my-leave.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                <x-icon name="plus" class="size-4"/> Ajukan Cuti/Izin
            </a>
        </section>

        @if ($balances)
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($balances as $balance)
                    <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">{{ $balance['type']->name }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-950">{{ $balance['remaining'] }}<span class="text-base font-normal text-gray-400"> / {{ $balance['quota'] }} hari</span></p>
                        <p class="mt-1 text-xs text-gray-400">Terpakai {{ $balance['used'] }} hari</p>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($pendingForMe->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-200 bg-amber-50 px-5 py-4">
                    <h2 class="text-base font-semibold text-amber-900">Perlu Persetujuan Anda ({{ $pendingForMe->count() }})</h2>
                    <p class="mt-1 text-sm text-amber-700">Pengajuan dari bawahan Anda yang menunggu persetujuan.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th class="text-right">Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($pendingForMe as $req)
                                <tr>
                                    <td class="font-medium text-gray-950">{{ $req->employee?->full_name }}</td>
                                    <td>{{ $req->leaveType?->name }}</td>
                                    <td>{{ $req->start_date->format('d M Y') }} – {{ $req->end_date->format('d M Y') }} <span class="text-xs text-gray-500">({{ $req->days }} hari)</span></td>
                                    <td class="text-right">
                                        <x-action-menu>
                                            <form method="POST" action="{{ route('my-leave.approve', $req) }}" data-no-confirm="true">@csrf @method('PATCH')<button type="submit" class="action-menu-item"><x-icon name="user-check"/> Setujui</button></form>
                                            <form method="POST" action="{{ route('my-leave.reject', $req) }}" data-no-confirm="true">@csrf @method('PATCH')<button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="user-x"/> Tolak</button></form>
                                        </x-action-menu>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Pengajuan Saya</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Jenis</th><th>Periode</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($myRequests as $req)
                            <tr>
                                <td class="font-medium text-gray-950">{{ $req->leaveType?->name }}</td>
                                <td>{{ $req->start_date->format('d M Y') }} – {{ $req->end_date->format('d M Y') }} <span class="text-xs text-gray-500">({{ $req->days }} hari)</span></td>
                                <td>
                                    <x-status-badge :tone="$req->status->tone()">{{ $req->status->label() }}</x-status-badge>
                                    @if ($req->decision_notes)<p class="mt-1 text-xs text-gray-500">{{ $req->decision_notes }}</p>@endif
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($req->status->isPending())
                                            <form method="POST" action="{{ route('my-leave.cancel', $req) }}" data-no-confirm="true" class="inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Batalkan</button>
                                            </form>
                                        @endif
                                        {{-- Hanya pengaju yang boleh menghapus, dan hanya bila belum disetujui. --}}
                                        @if ($req->status !== \App\Enums\LeaveRequestStatus::Approved)
                                            <form method="POST" action="{{ route('my-leave.destroy', $req) }}" onsubmit="return confirm('Hapus pengajuan ini?')" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"><x-icon name="trash"/> Hapus</button>
                                            </form>
                                        @elseif (! $req->status->isPending())
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="cell-empty">Belum ada pengajuan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
