@php
    $workDays = $days->filter(function ($day) use ($schedules, $leaves) {
        $key = $day->toDateString();
        $schedule = $schedules[$key] ?? null;

        return $schedule && ! $schedule->is_day_off && ! ($leaves[$key] ?? null);
    })->count();

    $leaveDays = $days->filter(fn ($day) => (bool) ($leaves[$day->toDateString()] ?? null))->count();
    $offDays = $days->filter(function ($day) use ($schedules, $leaves) {
        $key = $day->toDateString();
        $schedule = $schedules[$key] ?? null;

        return $schedule && $schedule->is_day_off && ! ($leaves[$key] ?? null);
    })->count();
@endphp

<x-layouts.app title="Jadwal {{ $employee->full_name }} - {{ config('app.name', 'HRIS') }}" heading="Jadwal Karyawan">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Jadwal per karyawan · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $employee->full_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $employee->employee_number }} ·
                    {{ $employee->jobPosition?->name ?? 'Jabatan belum diisi' }} ·
                    {{ $employee->department?->name ?? 'Divisi belum diisi' }} ·
                    {{ $employee->branch?->name ?? 'Lokasi belum diisi' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('attendance.schedules.index', ['month' => $month->format('Y-m'), 'branch_id' => $employee->branch_id]) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali ke Roster</a>
                @can('employees.view')<a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Profil Karyawan</a>@endcan
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        {{-- Month navigation + month summary --}}
        <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" action="{{ route('attendance.schedules.show', $employee) }}" class="flex items-center gap-2">
                <a href="{{ route('attendance.schedules.show', ['employee' => $employee, 'month' => $prevMonth]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Bulan sebelumnya">‹</a>
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <a href="{{ route('attendance.schedules.show', ['employee' => $employee, 'month' => $nextMonth]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Bulan berikutnya">›</a>
            </form>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <span class="text-gray-600"><span class="font-semibold text-gray-900">{{ $workDays }}</span> hari kerja</span>
                <span class="text-gray-600"><span class="font-semibold text-gray-900">{{ $offDays }}</span> hari libur</span>
                <span class="text-gray-600"><span class="font-semibold text-amber-700">{{ $leaveDays }}</span> hari cuti/izin</span>
            </div>
        </section>

        {{-- Pattern assignments: which pattern produced this month's shifts --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Penugasan Pola</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pola</th><th>Periode</th></tr></thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $assignment->pattern?->name ?? 'Pola dihapus' }} <span class="text-xs text-gray-500">({{ $assignment->pattern?->type->label() }})</span></td>
                                <td class="text-sm text-gray-600">{{ $assignment->start_date->translatedFormat('d M Y') }} – {{ $assignment->end_date ? $assignment->end_date->translatedFormat('d M Y') : 'seterusnya' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="cell-empty">Karyawan ini belum pernah ditugaskan pola jadwal.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Day-by-day schedule --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Jadwal Harian {{ $month->translatedFormat('F Y') }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">Cuti/izin yang sudah disetujui ditandai kuning — pada hari itu karyawan tidak masuk meski ada shift di jadwal.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Shift</th><th>Jam</th><th>Sumber</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @foreach ($days as $day)
                            @php
                                $key = $day->toDateString();
                                $schedule = $schedules[$key] ?? null;
                                $holiday = $holidays[$key] ?? null;
                                $leave = $leaves[$key] ?? null;
                                $isManual = $schedule && $schedule->source === \App\Enums\ScheduleSource::Manual;
                            @endphp
                            <tr @class(['bg-amber-50/70' => $leave, 'bg-red-50/50' => ! $leave && $holiday])>
                                <td class="whitespace-nowrap">
                                    <span class="font-medium text-gray-900">{{ $day->translatedFormat('d M Y') }}</span>
                                    <span class="ml-1 text-xs text-gray-500">{{ $day->translatedFormat('l') }}</span>
                                </td>
                                <td>
                                    @if ($leave)
                                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $leave->leaveType?->code ?? 'CUTI' }}</span>
                                    @elseif ($schedule && ! $schedule->is_day_off)
                                        <span class="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ $schedule->shift?->code ?? '?' }}</span>
                                        <span class="ml-1 text-sm text-gray-700">{{ $schedule->shift?->name }}</span>
                                    @elseif ($schedule)
                                        <span class="text-sm text-gray-500">Libur</span>
                                    @else
                                        <span class="text-sm text-gray-400">Belum dijadwalkan</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-gray-600">
                                    @if (! $leave && $schedule && ! $schedule->is_day_off && $schedule->shift)
                                        {{ \Illuminate\Support\Str::substr($schedule->shift->start_time, 0, 5) }}–{{ \Illuminate\Support\Str::substr($schedule->shift->end_time, 0, 5) }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="text-sm text-gray-600">
                                    @if (! $schedule)
                                        <span class="text-gray-400">—</span>
                                    @elseif ($isManual)
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-blue-300">Manual</span>
                                    @else
                                        <span class="text-xs text-gray-500">Pola</span>
                                    @endif
                                </td>
                                <td class="text-sm text-gray-600">
                                    @if ($leave)
                                        <span class="font-medium text-amber-800">{{ $leave->leaveType?->name ?? 'Cuti' }} disetujui</span>
                                        <span class="text-xs text-gray-500">({{ $leave->start_date->translatedFormat('d M') }} – {{ $leave->end_date->translatedFormat('d M') }})</span>
                                        @if ($schedule && ! $schedule->is_day_off)
                                            <span class="block text-xs text-gray-500">Jadwal semula: {{ $schedule->shift?->name }}</span>
                                        @endif
                                    @elseif ($holiday)
                                        <span class="text-red-600">{{ $holiday->name }}</span>
                                    @elseif ($schedule?->note)
                                        {{ $schedule->note }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
