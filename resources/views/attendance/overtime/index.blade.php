<x-layouts.app title="Pemantauan Lembur - {{ config('app.name', 'HRIS') }}" heading="Pemantauan Lembur">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Lembur · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Pemantauan Lembur</h1>
                <p class="mt-1 text-sm text-gray-500">Lembur diajukan karyawan dan disetujui oleh atasan langsung. Halaman ini untuk memantau; lembur disetujui masuk ke rekap.</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($pendingCount > 0)<x-status-badge tone="warning">{{ $pendingCount }} menunggu atasan</x-status-badge>@endif
                <a href="{{ route('attendance.overtime.recap', ['month' => $month->format('Y-m'), 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Rekap Lembur</a>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.overtime.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.overtime.index', array_merge(request()->query(), ['month' => $prevMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('attendance.overtime.index', array_merge(request()->query(), ['month' => $nextMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
                </div>
                <select name="status" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
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
                <input name="search" value="{{ $search }}" placeholder="Cari nama / NIK" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Jam</th><th>Diajukan</th><th>Disetujui</th><th>Atasan</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($requests as $req)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $req->employee?->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $req->employee?->employee_number }}</p></td>
                                <td class="text-sm text-gray-700">{{ $req->work_date->translatedFormat('D, d M') }}</td>
                                <td class="text-sm text-gray-600">{{ $req->time_range_label ?? '—' }}</td>
                                <td class="text-sm text-gray-700">{{ intdiv($req->requested_minutes, 60) }}j {{ $req->requested_minutes % 60 }}m</td>
                                <td class="text-sm font-medium text-gray-800">{{ $req->status === 'approved' ? intdiv($req->approved_minutes, 60).'j '.($req->approved_minutes % 60).'m' : '—' }}</td>
                                <td class="text-sm text-gray-600">{{ $req->supervisor?->full_name ?? '—' }}</td>
                                <td><x-status-badge :tone="$req->status_tone">{{ $req->status_label }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada pengajuan lembur pada bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
