<x-layouts.app title="Rekap Cuti - {{ config('app.name', 'HRIS') }}" heading="Rekap Cuti">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.index') }}" class="hover:text-gray-700">Laporan</a> · Tahun {{ $year }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Rekap Cuti</h1>
                <p class="mt-1 text-sm text-gray-500">Cuti disetujui yang terpakai & sisa kuota per karyawan dalam setahun.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.leave.pdf', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <x-icon name="download" class="size-4"/> PDF
                </a>
                <a href="{{ route('reports.leave.export', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <x-icon name="download" class="size-4"/> Excel
                </a>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('reports.leave') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.leave', array_merge(request()->query(), ['year' => $year - 1])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <span class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-800">{{ $year }}</span>
                    <a href="{{ route('reports.leave', array_merge(request()->query(), ['year' => $year + 1])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
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
                    <input type="hidden" name="year" value="{{ $year }}">
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            @foreach ($types as $type)
                                <th class="text-center">{{ $type->name }}</th>
                            @endforeach
                            <th class="text-right">Total Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $e = $row['employee']; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('reports.leave.detail', ['employee' => $e->id, 'year' => $year]) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $e->full_name }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $e->employee_number }} · {{ $e->department?->name ?? '—' }}</p>
                                </td>
                                @foreach ($types as $type)
                                    @php $cell = $row['cells'][$type->id]; @endphp
                                    <td class="text-center text-sm">
                                        <span class="{{ $cell['used'] > 0 ? 'font-medium text-gray-900' : 'text-gray-400' }}">{{ $cell['used'] }}</span>
                                        @if ($cell['remaining'] !== null)
                                            <span class="mt-0.5 block text-[11px] {{ $cell['remaining'] <= 0 ? 'text-red-500' : 'text-gray-400' }}">sisa {{ $cell['remaining'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-right text-sm font-semibold text-gray-800">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $types->count() + 2 }}" class="cell-empty">Belum ada karyawan / data cuti pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-gray-400">Angka utama = hari cuti disetujui yang terpakai. "sisa" = kuota tahunan dikurangi yang terpakai (hanya untuk jenis cuti yang memakai kuota).</p>
    </div>
</x-layouts.app>
