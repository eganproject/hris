<x-layouts.app title="Log Absensi - {{ config('app.name', 'HRIS') }}" heading="Log Absensi">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.index') }}" class="hover:text-gray-700">Laporan</a> · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Log Absensi</h1>
                <p class="mt-1 text-sm text-gray-500">Rincian kehadiran harian per karyawan lengkap dengan jam masuk & jam keluar.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.attendance-log.pdf', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <x-icon name="download" class="size-4"/> PDF
                </a>
                <a href="{{ route('reports.attendance-log.export', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <x-icon name="download" class="size-4"/> Excel
                </a>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('reports.attendance-log') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.attendance-log', array_merge(request()->query(), ['month' => $prevMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('reports.attendance-log', array_merge(request()->query(), ['month' => $nextMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select name="branch_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="department_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Rincian Harian</h2>
                <span class="text-xs text-gray-500">{{ $rows->count() }} baris</span>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Karyawan</th>
                            <th>Divisi</th>
                            <th>Shift</th>
                            <th class="text-center">Jam Masuk</th>
                            <th class="text-center">Jam Keluar</th>
                            <th class="text-right">Telat</th>
                            <th class="text-right">Plg Cepat</th>
                            <th class="text-right">Jam Kerja</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $record)
                            <tr>
                                <td class="whitespace-nowrap text-sm text-gray-700">{{ $record->work_date->translatedFormat('D, d M Y') }}</td>
                                <td>
                                    <p class="font-medium text-gray-900">{{ $record->employee?->full_name ?? '—' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $record->employee?->employee_number ?? '—' }}</p>
                                </td>
                                <td class="text-sm text-gray-600">{{ $record->employee?->department?->name ?? '—' }}</td>
                                <td class="text-sm text-gray-600">{{ $record->shift?->code ?? '—' }}</td>
                                <td class="text-center text-sm font-medium {{ $record->clock_in ? 'text-gray-900' : 'text-gray-300' }}">{{ $record->clock_in_label }}</td>
                                <td class="text-center text-sm font-medium {{ $record->clock_out ? 'text-gray-900' : 'text-gray-300' }}">{{ $record->clock_out_label }}</td>
                                <td class="text-right text-sm {{ $record->late_minutes > 0 ? 'font-medium text-amber-600' : 'text-gray-400' }}">{{ $record->late_minutes }} m</td>
                                <td class="text-right text-sm {{ $record->early_leave_minutes > 0 ? 'font-medium text-amber-600' : 'text-gray-400' }}">{{ $record->early_leave_minutes }} m</td>
                                <td class="text-right text-sm text-gray-700">{{ intdiv($record->work_minutes, 60) }}j {{ $record->work_minutes % 60 }}m</td>
                                <td><x-status-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="cell-empty">Belum ada data kehadiran pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-gray-400">Jam masuk/keluar diambil dari punch pertama & terakhir pada hari kerja. Tanda “–” berarti tidak ada punch (mis. Alfa, Cuti, atau Libur).</p>
    </div>
</x-layouts.app>
