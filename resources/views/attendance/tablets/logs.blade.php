<x-layouts.app title="Riwayat Absen Tablet - {{ config('app.name', 'HRIS') }}" heading="Tablet Absensi Wajah">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Absensi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Riwayat absen tablet</h1>
                <p class="mt-1 text-sm text-gray-500">Setiap absen dari tablet beserta foto buktinya. Absen pakai NIK + PIN, kecocokan wajah rendah, atau cek wajah asli yang meragukan hanya diberi tanda, tanpa perlu persetujuan HR.</p>
            </div>
        </section>

        <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @include('attendance.tablets._tabs', ['active' => $tab])

            <form method="GET" action="{{ route('attendance.tablets.logs') }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-medium text-gray-600">Cari karyawan</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nama / NIK" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="date" class="block text-xs font-medium text-gray-600">Tanggal</label>
                    <input type="date" id="date" name="date" value="{{ $filters['date'] }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex gap-2 lg:col-span-3 lg:justify-end">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('attendance.tablets.logs', ['tab' => $tab]) }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Foto</th><th>Karyawan</th><th>Waktu</th><th>Tablet</th><th>Skor</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>
                                    @if ($log->photo_path)
                                        <a href="{{ route('attendance.tablets.logs.photo', $log) }}" target="_blank" rel="noopener" title="Buka foto">
                                            <img src="{{ route('attendance.tablets.logs.photo', $log) }}" alt="Foto absen {{ $log->employee?->full_name }}" class="size-8 rounded object-cover ring-1 ring-gray-200 transition hover:ring-primary">
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-300">—</span>
                                    @endif
                                </td>
                                <td><p class="font-medium text-gray-950">{{ $log->employee?->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $log->employee?->employee_number }}</p></td>
                                <td class="text-sm text-gray-700">
                                    <p class="font-medium">{{ ['in' => 'Masuk', 'out' => 'Pulang'][$log->type] ?? 'Absen' }} {{ $log->captured_at->format('H:i') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $log->captured_at->translatedFormat('d M Y') }}@if ($log->received_at->diffInMinutes($log->captured_at, true) > 5) · terkirim {{ $log->received_at->format('d/m H:i') }}@endif</p>
                                </td>
                                <td class="text-sm text-gray-700"><p>{{ $log->tablet?->name ?? '–' }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $log->branch?->name }}</p></td>
                                <td class="text-sm text-gray-700">
                                    @if ($log->method === 'pin')
                                        NIK + PIN
                                    @else
                                        <p>Wajah {{ $log->match_score !== null ? number_format($log->match_score, 2, ',', '.') : '–' }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">Wajah asli {{ $log->liveness_checked && $log->liveness_score !== null ? number_format($log->liveness_score, 2, ',', '.') : 'tidak dicek' }}</p>
                                    @endif
                                </td>
                                <td class="text-sm">
                                    @if ($log->result !== 'accepted')
                                        <x-status-badge :tone="$log->result === 'rejected' ? 'danger' : 'neutral'">{{ $log->resultLabel() }}</x-status-badge>
                                        <p class="mt-1 max-w-xs text-xs text-gray-600">{{ $log->reject_reason }}</p>
                                    @elseif ($log->flags)
                                        <div class="flex max-w-xs flex-wrap gap-1">
                                            @foreach ($log->flagLabels() as $flag)
                                                <x-status-badge tone="warning">{{ $flag }}</x-status-badge>
                                            @endforeach
                                        </div>
                                    @else
                                        <x-status-badge tone="success">Sesuai</x-status-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">{{ $tab === 'flagged' ? 'Tidak ada absen bertanda.' : 'Belum ada absen dari tablet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $logs->links() }}</div>
        </section>
    </div>
</x-layouts.app>
