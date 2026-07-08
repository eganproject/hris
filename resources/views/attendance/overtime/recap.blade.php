<x-layouts.app title="Rekap Lembur - {{ config('app.name', 'HRIS') }}" heading="Rekap Lembur">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Lembur · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Rekap Lembur</h1>
                <p class="mt-1 text-sm text-gray-500">Total lembur yang <span class="font-medium">disetujui</span> per karyawan.</p>
            </div>
            <a href="{{ route('attendance.overtime.index', ['month' => $month->format('Y-m'), 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Persetujuan Lembur</a>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.overtime.recap') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.overtime.recap', ['month' => $prevMonth, 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('attendance.overtime.recap', ['month' => $nextMonth, 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
                </div>
                <select name="branch_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Semua lokasi</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">Total Lembur Disetujui Bulan Ini</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950">{{ floor($totalMinutes / 60) }} jam {{ $totalMinutes % 60 }} menit</p>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Hari Lembur</th><th class="text-right">Total Lembur</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $minutes = (int) $row->minutes; @endphp
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $employees[$row->employee_id]?->full_name ?? '—' }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $employees[$row->employee_id]?->employee_number }}</p></td>
                                <td class="text-sm text-gray-700">{{ $row->days }} hari</td>
                                <td class="text-right text-sm font-medium text-gray-800">{{ floor($minutes / 60) }}j {{ $minutes % 60 }}m</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cell-empty">Belum ada lembur disetujui pada bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
