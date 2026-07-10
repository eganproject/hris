<x-layouts.app title="Detail Kehadiran - {{ $employee->full_name }}" heading="Detail Kehadiran">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.attendance', ['month' => $month->format('Y-m')]) }}" class="hover:text-gray-700">‹ Rekap Kehadiran</a> · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $employee->full_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $employee->employee_number }} · {{ $employee->department?->name ?? '—' }} · {{ $employee->jobPosition?->name ?? '—' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.attendance.detail', ['employee' => $employee, 'month' => $prevMonth]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                <span class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-800">{{ $month->format('Y-m') }}</span>
                <a href="{{ route('reports.attendance.detail', ['employee' => $employee, 'month' => $nextMonth]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            <x-stat-card label="Total Hari" :value="$summary['total_hari']" tone="primary"><x-icon name="calendar-clock" class="size-5"/></x-stat-card>
            <x-stat-card label="Hadir" :value="$summary['hadir']" tone="emerald"><x-icon name="user-check" class="size-5"/></x-stat-card>
            <x-stat-card label="Terlambat" :value="$summary['terlambat']" tone="amber"><x-icon name="clock" class="size-5"/></x-stat-card>
            <x-stat-card label="Alfa" :value="$summary['alfa']" tone="rose"><x-icon name="user-x" class="size-5"/></x-stat-card>
            <x-stat-card label="Total Telat" :value="$summary['terlambat_menit'].' m'" tone="sky"><x-icon name="clock" class="size-5"/></x-stat-card>
            <x-stat-card label="Jam Kerja" :value="intdiv($summary['kerja_menit'],60).'j '.($summary['kerja_menit']%60).'m'" tone="primary"><x-icon name="briefcase" class="size-5"/></x-stat-card>
            <x-stat-card label="Lembur Disetujui" :value="intdiv($summary['lembur_menit'],60).'j '.($summary['lembur_menit']%60).'m'" tone="primary"><x-icon name="clock" class="size-5"/></x-stat-card>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th><th>Shift</th><th>Status</th>
                            <th class="text-center">Masuk</th><th class="text-center">Pulang</th>
                            <th class="text-right">Telat</th><th class="text-right">Plg Cepat</th>
                            <th class="text-right">Jam Kerja</th><th class="text-right">Lembur Disetujui</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $record->work_date->translatedFormat('D, d M') }}</td>
                                <td class="text-sm text-gray-600">{{ $record->shift?->code ?? '—' }}</td>
                                <td><x-status-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-status-badge></td>
                                <td class="text-center text-sm text-gray-700">{{ $record->clock_in_label }}</td>
                                <td class="text-center text-sm text-gray-700">{{ $record->clock_out_label }}</td>
                                <td class="text-right text-sm {{ $record->late_minutes > 0 ? 'font-medium text-amber-600' : 'text-gray-400' }}">{{ $record->late_minutes }} m</td>
                                <td class="text-right text-sm {{ $record->early_leave_minutes > 0 ? 'font-medium text-amber-600' : 'text-gray-400' }}">{{ $record->early_leave_minutes }} m</td>
                                <td class="text-right text-sm text-gray-700">{{ intdiv($record->work_minutes, 60) }}j {{ $record->work_minutes % 60 }}m</td>
                                @php $otMenit = (int) ($approvedOvertime[$record->work_date->toDateString()] ?? 0); @endphp
                                <td class="text-right text-sm {{ $otMenit > 0 ? 'font-medium text-gray-800' : 'text-gray-400' }}">{{ intdiv($otMenit, 60) }}j {{ $otMenit % 60 }}m</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="cell-empty">Belum ada data kehadiran pada bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
