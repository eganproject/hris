<x-layouts.app title="Koreksi Absensi - {{ config('app.name', 'HRIS') }}" heading="Koreksi Absensi">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Peninjauan HR</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Koreksi Absensi</h1>
                <p class="mt-1 text-sm text-gray-500">Pengajuan koreksi jam dari karyawan. Menyetujui akan memperbarui absensi harian.</p>
            </div>
            @if ($pendingCount > 0)<x-status-badge tone="warning">{{ $pendingCount }} menunggu</x-status-badge>@endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.corrections.index') }}" class="flex flex-wrap items-center gap-2">
                @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $value => $label)
                    <a href="{{ route('attendance.corrections.index', ['status' => $value]) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $status === $value, 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $status !== $value])>{{ $label }}</a>
                @endforeach
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Usulan Jam</th><th>Alasan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($corrections as $c)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $c->employee?->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $c->employee?->employee_number }}</p></td>
                                <td class="text-sm text-gray-700">{{ $c->work_date->translatedFormat('d M Y') }}</td>
                                <td class="text-sm text-gray-700">{{ $c->requested_clock_in ?? '—' }} / {{ $c->requested_clock_out ?? '—' }}</td>
                                <td class="max-w-xs truncate text-sm text-gray-600" title="{{ $c->reason }}">{{ $c->reason }}</td>
                                <td>
                                    <x-status-badge :tone="$c->status_tone">{{ $c->status_label }}</x-status-badge>
                                    @if ($c->reviewer)<p class="mt-1 text-xs text-gray-400">oleh {{ $c->reviewer->name }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @can('corrections.update')
                                        @if ($c->isPending())
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('attendance.corrections.approve', $c) }}" onsubmit="return confirm('Setujui koreksi & perbarui absensi?')">@csrf @method('PATCH')<button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button></form>
                                                <form method="POST" action="{{ route('attendance.corrections.reject', $c) }}">@csrf @method('PATCH')<button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $c->decided_at?->format('d M H:i') }}</span>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Tidak ada pengajuan koreksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $corrections->links() }}</div>
        </section>
    </div>
</x-layouts.app>
