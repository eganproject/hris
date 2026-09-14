<x-layouts.app title="Detail Cuti - {{ $employee->full_name }}" heading="Detail Cuti">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.leave', ['year' => $year]) }}" class="hover:text-gray-700">‹ Rekap Cuti</a> · Tahun {{ $year }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $employee->full_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $employee->employee_number }} · {{ $employee->department?->name ?? '—' }} · {{ $employee->jobPosition?->name ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.leave.detail', ['employee' => $employee->id, 'year' => $year - 1]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Tahun sebelumnya">‹</a>
                    <span class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-800">{{ $year }}</span>
                    <a href="{{ route('reports.leave.detail', ['employee' => $employee->id, 'year' => $year + 1]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Tahun berikutnya">›</a>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-5 py-3 text-center shadow-sm">
                    <p class="text-xs font-medium text-gray-500">Total Cuti Disetujui {{ $year }}</p>
                    <p class="mt-0.5 text-2xl font-semibold text-gray-950">{{ $approvedDays }} <span class="text-sm font-normal text-gray-500">hari</span></p>
                </div>
            </div>
        </section>

        {{-- Saldo per jenis cuti. "Sisa" = kuota dikurangi yang disetujui, sama dengan
             angka di Rekap Cuti; yang masih menunggu ditulis terpisah. --}}
        <section>
            <h2 class="text-sm font-semibold text-gray-900">Sisa Kuota Cuti</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($balances as $balance)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" data-leave-balance="{{ $balance['type']->id }}">
                        <p class="text-sm font-medium text-gray-700">{{ $balance['type']->name }}</p>
                        @if ($balance['quota'] !== null)
                            <p class="mt-1 text-2xl font-semibold {{ $balance['remaining'] <= 0 ? 'text-red-600' : 'text-gray-950' }}">
                                {{ $balance['remaining'] }}<span class="text-sm font-normal text-gray-400"> / {{ $balance['quota'] }} hari</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Terpakai {{ $balance['used'] }} hari
                                @if ($balance['pending'] > 0)
                                    · <span class="text-amber-700">menunggu {{ $balance['pending'] }} hari</span>
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-2xl font-semibold text-gray-950">{{ $balance['used'] }}<span class="text-sm font-normal text-gray-400"> hari</span></p>
                            <p class="mt-1 text-xs text-gray-500">
                                Tanpa kuota
                                @if ($balance['pending'] > 0)
                                    · <span class="text-amber-700">menunggu {{ $balance['pending'] }} hari</span>
                                @endif
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Belum ada jenis cuti yang memakai kuota.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Riwayat Pengajuan Cuti</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Jenis Cuti</th><th>Mulai</th><th>Selesai</th><th class="text-center">Hari</th><th>Status</th><th class="text-center">Sisa Kuota</th><th>Alasan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $req)
                            <tr>
                                <td class="text-sm font-medium text-gray-900">{{ $req->leaveType?->name ?? '—' }}</td>
                                <td class="text-sm text-gray-700">{{ $req->start_date->translatedFormat('d M Y') }}</td>
                                <td class="text-sm text-gray-700">{{ $req->end_date->translatedFormat('d M Y') }}</td>
                                <td class="text-center text-sm font-medium text-gray-800">{{ $req->days }}</td>
                                <td><x-status-badge :tone="$req->status->tone()">{{ $req->status->label() }}</x-status-badge></td>
                                <td class="text-center text-sm {{ isset($remainingAfter[$req->id]) && $remainingAfter[$req->id] <= 0 ? 'font-medium text-red-600' : 'text-gray-700' }}">{{ $remainingAfter[$req->id] ?? '—' }}</td>
                                <td class="text-sm text-gray-500">{{ $req->reason ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada pengajuan cuti pada tahun ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-gray-400">"Sisa Kuota" pada riwayat = sisa kuota jenis cuti itu setelah cuti tersebut disetujui, dihitung urut tanggal dari awal tahun. Pengajuan yang menunggu, ditolak, dibatalkan, atau jenis cuti tanpa kuota tidak mengurangi sisa (—).</p>
    </div>
</x-layouts.app>
