<x-layouts.app title="Kuota Cuti - {{ config('app.name', 'HRIS') }}" heading="Kuota Cuti">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('attendance.leave-types.index') }}" class="hover:text-gray-700">Jenis Cuti</a> · Tahun {{ $year }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Kuota Cuti per Karyawan</h1>
                <p class="mt-1 text-sm text-gray-500">Sesuaikan jatah cuti tahunan tiap karyawan. Kosongkan/samakan dengan default agar mengikuti kuota default.</p>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.leave-balances.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.leave-balances.index', array_merge(request()->query(), ['year' => $year - 1])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <span class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-800">{{ $year }}</span>
                    <a href="{{ route('attendance.leave-balances.index', array_merge(request()->query(), ['year' => $year + 1])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
                    <input type="hidden" name="year" value="{{ $year }}">
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

        @if ($types->isEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada jenis cuti yang "memakai kuota". <a href="{{ route('attendance.leave-types.index') }}" class="font-medium underline">Atur jenis cuti</a> dulu.
            </div>
        @else
            <form method="POST" action="{{ route('attendance.leave-balances.update', ['year' => $year, 'branch_id' => $branchId, 'department_id' => $departmentId]) }}">
                @csrf
                @method('PUT')
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    @foreach ($types as $type)
                                        <th class="text-center">{{ $type->name }}<br><span class="text-[10px] font-normal normal-case text-gray-400">default {{ $type->default_quota_days ?? 0 }} hari</span></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($employees as $employee)
                                    <tr>
                                        <td>
                                            <p class="font-medium text-gray-950">{{ $employee->full_name }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $employee->employee_number }} · {{ $employee->department?->name ?? '—' }}</p>
                                        </td>
                                        @foreach ($types as $type)
                                            @php
                                                $override = $overrides[$employee->id][$type->id] ?? null;
                                                $value = $override?->quota_days ?? ($type->default_quota_days ?? 0);
                                            @endphp
                                            <td class="text-center">
                                                <input type="number" min="0" max="365" name="quota[{{ $employee->id }}][{{ $type->id }}]" value="{{ $value }}" class="w-20 rounded-md border px-2 py-1.5 text-center text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 {{ $override ? 'border-primary/40 bg-primary-soft' : 'border-gray-300' }}" title="{{ $override ? 'Kuota khusus' : 'Mengikuti default' }}">
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $types->count() + 1 }}" class="cell-empty">Tidak ada karyawan pada filter ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if ($employees->isNotEmpty())
                    <div class="mt-4 flex items-center justify-between gap-4">
                        <p class="text-xs text-gray-400">Kolom ber-latar menandai kuota khusus (berbeda dari default).</p>
                        <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Kuota</button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-layouts.app>
