<x-layouts.app title="Detail Cuti - {{ $employee->full_name }}" heading="Detail Cuti">
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.leave', ['year' => $year]) }}" class="hover:text-gray-700">‹ Rekap Cuti</a> · Tahun {{ $year }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $employee->full_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $employee->employee_number }} · {{ $employee->department?->name ?? '—' }} · {{ $employee->jobPosition?->name ?? '—' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-5 py-3 text-center shadow-sm">
                <p class="text-xs font-medium text-gray-500">Total Cuti Disetujui {{ $year }}</p>
                <p class="mt-0.5 text-2xl font-semibold text-gray-950">{{ $approvedDays }} <span class="text-sm font-normal text-gray-500">hari</span></p>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Jenis Cuti</th><th>Mulai</th><th>Selesai</th><th class="text-center">Hari</th><th>Status</th><th>Alasan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $req)
                            <tr>
                                <td class="text-sm font-medium text-gray-900">{{ $req->leaveType?->name ?? '—' }}</td>
                                <td class="text-sm text-gray-700">{{ $req->start_date->translatedFormat('d M Y') }}</td>
                                <td class="text-sm text-gray-700">{{ $req->end_date->translatedFormat('d M Y') }}</td>
                                <td class="text-center text-sm font-medium text-gray-800">{{ $req->days }}</td>
                                <td><x-status-badge :tone="$req->status->tone()">{{ $req->status->label() }}</x-status-badge></td>
                                <td class="text-sm text-gray-500">{{ $req->reason ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada pengajuan cuti pada tahun ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
