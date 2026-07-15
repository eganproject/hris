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

        <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $value => $label)
                    <a href="{{ route('attendance.corrections.index', array_merge(request()->except('page'), ['status' => $value])) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $status === $value, 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $status !== $value])>{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('attendance.corrections.index') }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-medium text-gray-600">Cari karyawan</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nama / NIK" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="branch_id" class="block text-xs font-medium text-gray-600">Lokasi</label>
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($filters['branchId'] === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-xs font-medium text-gray-600">Divisi</label>
                    <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($filters['departmentId'] === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-xs font-medium text-gray-600">Dari tanggal</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['dateFrom'] }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-medium text-gray-600">Sampai tanggal</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['dateTo'] }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('attendance.corrections.index', ['status' => $status]) }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
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
