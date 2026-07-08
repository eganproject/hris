<x-layouts.app title="Persetujuan Lembur - {{ config('app.name', 'HRIS') }}" heading="Persetujuan Lembur">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Lembur · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Persetujuan Lembur</h1>
                <p class="mt-1 text-sm text-gray-500">Lembur dihitung otomatis dari absensi. Setujui agar masuk rekap.</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($pendingCount > 0)<x-status-badge tone="warning">{{ $pendingCount }} belum diputuskan</x-status-badge>@endif
                <a href="{{ route('attendance.overtime.recap', ['month' => $month->format('Y-m'), 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Rekap Lembur</a>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.overtime.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.overtime.index', ['month' => $prevMonth, 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('attendance.overtime.index', ['month' => $nextMonth, 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
                </div>
                <select name="branch_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Semua lokasi</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Shift</th><th>Lembur</th><th>Status</th><th class="text-right">Keputusan</th></tr></thead>
                    <tbody>
                        @forelse ($attendances as $att)
                            @php
                                $key = $att->employee_id.'|'.$att->work_date->toDateString();
                                $appr = $approvals[$key] ?? null;
                                $computed = $att->overtime_minutes;
                            @endphp
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $att->employee?->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $att->employee?->employee_number }}</p></td>
                                <td class="text-sm text-gray-700">{{ $att->work_date->translatedFormat('D, d M') }}</td>
                                <td class="text-sm text-gray-600">{{ $att->shift?->code ?? '—' }}</td>
                                <td class="text-sm font-medium text-gray-800">{{ floor($computed / 60) }}j {{ $computed % 60 }}m
                                    @if ($appr && $appr->status === 'approved' && $appr->approved_minutes !== $computed)<span class="text-xs font-normal text-emerald-600">(disetujui {{ floor($appr->approved_minutes / 60) }}j {{ $appr->approved_minutes % 60 }}m)</span>@endif
                                </td>
                                <td>
                                    @if ($appr)<x-status-badge :tone="$appr->status_tone">{{ $appr->status_label }}</x-status-badge>@else<span class="text-xs text-gray-400">Belum diputuskan</span>@endif
                                </td>
                                <td class="text-right">
                                    @can('attendance.update')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <form method="POST" action="{{ route('attendance.overtime.approve') }}" class="flex items-center gap-1.5">
                                                @csrf
                                                <input type="hidden" name="employee_id" value="{{ $att->employee_id }}">
                                                <input type="hidden" name="work_date" value="{{ $att->work_date->toDateString() }}">
                                                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                                                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                                <input type="number" name="approved_minutes" min="0" max="1440" value="{{ $appr->approved_minutes ?? $computed }}" class="w-16 rounded-md border border-gray-300 px-2 py-1 text-xs shadow-xs outline-none focus:border-primary" title="Menit disetujui">
                                                <button type="submit" class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('attendance.overtime.reject') }}">
                                                @csrf
                                                <input type="hidden" name="employee_id" value="{{ $att->employee_id }}">
                                                <input type="hidden" name="work_date" value="{{ $att->work_date->toDateString() }}">
                                                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                                                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                                <button type="submit" class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Tidak ada lembur pada bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
