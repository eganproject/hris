<x-layouts.app title="Karyawan {{ $pattern->name }} - {{ config('app.name', 'HRIS') }}" heading="Karyawan pada Pola">
    @php
        $today = \Illuminate\Support\Carbon::today();
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">
                    <a href="{{ route('attendance.schedule-patterns.index', $pattern->trashed() ? ['status' => 'archived'] : []) }}" class="hover:text-primary hover:underline">Pola Jadwal</a>
                    <span class="mx-1 text-gray-300">/</span> Karyawan
                </p>
                <h1 class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-semibold text-gray-950">
                    {{ $pattern->name }}
                    @if ($pattern->trashed())
                        <x-status-badge tone="warning">Diarsipkan</x-status-badge>
                    @elseif (! $pattern->is_active)
                        <x-status-badge tone="neutral">Nonaktif</x-status-badge>
                    @endif
                    @if ($isGlobalDefault)<x-status-badge tone="info">Pola jam kantor default</x-status-badge>@endif
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $pattern->code }} · {{ $pattern->type->label() }}
                    @if ($pattern->type === \App\Enums\SchedulePatternType::Rotating) · siklus {{ $pattern->cycle_length }} hari @endif
                    · {{ $employees->total() }} karyawan
                </p>
            </div>
            <div class="flex gap-2">
                @can('schedule-patterns.update')
                    @unless ($pattern->trashed())
                        <a href="{{ route('attendance.schedule-patterns.edit', $pattern) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit Pola</a>
                    @endunless
                @endcan
                @can('schedules.create')<a href="{{ route('attendance.schedules.assign') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tugaskan Pola</a>@endcan
            </div>
        </section>

        {{-- Isi polanya ditaruh di sini juga: pertanyaan "siapa yang pakai pola ini"
             hampir selalu disusul "memangnya polanya seperti apa". --}}
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Isi pola</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($pattern->days as $day)
                    @php
                        $label = $pattern->type === \App\Enums\SchedulePatternType::FixedWeekly
                            ? \Illuminate\Support\Carbon::now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY)->addDays($day->day_index)->translatedFormat('D')
                            : 'Hari '.($day->day_index + 1);
                    @endphp
                    <div @class([
                        'min-w-[84px] rounded-md border px-3 py-2 text-center',
                        'border-primary/20 bg-primary/5' => $day->shift_id,
                        'border-gray-200 bg-gray-50' => ! $day->shift_id,
                    ])>
                        <p class="text-[11px] font-medium uppercase text-gray-500">{{ $label }}</p>
                        <p class="mt-0.5 text-sm font-semibold {{ $day->shift_id ? 'text-gray-900' : 'text-gray-400' }}">{{ $day->shift?->code ?? 'Libur' }}</p>
                        @if ($day->shift)<p class="text-[11px] text-gray-500">{{ $day->shift->time_range_label }}</p>@endif
                        @if ($day->is_wfh)<p class="text-[11px] font-medium text-indigo-600">WFH</p>@endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($isGlobalDefault)
            <div class="flex items-start gap-2.5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <x-icon name="info" class="mt-0.5 size-4"/>
                <p>Pola ini dipakai sebagai pola jam kantor default di Pengaturan, jadi daftar berikut juga memuat karyawan jam kantor yang tidak memilih pola sendiri.</p>
            </div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.schedule-patterns.employees', $pattern) }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto_auto]">
                <input name="search" value="{{ $filters['search'] }}" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Cari nama atau NIK karyawan">
                <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2.5 text-sm text-gray-700">
                    <input type="checkbox" name="include_inactive" value="1" @checked($filters['include_inactive']) class="size-4 rounded border-gray-300 text-primary focus:ring-primary/30">
                    Termasuk nonaktif
                </label>
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('attendance.schedule-patterns.employees', $pattern) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Lokasi &amp; Divisi</th><th>Cara memakai</th><th>Periode</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php
                                $assignments = $employee->scheduleAssignments;
                                $viaOfficeHours = $employee->follows_office_hours;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('attendance.schedules.show', ['employee' => $employee]) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $employee->full_name }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $employee->employee_number }}</p>
                                </td>
                                <td class="text-xs text-gray-600">
                                    {{ $employee->branch?->name ?? '-' }}
                                    <p class="mt-0.5 text-gray-400">{{ $employee->department?->name ?? '-' }} · {{ $employee->jobPosition?->name ?? '-' }}</p>
                                </td>
                                <td class="text-xs">
                                    @if ($assignments->isNotEmpty())
                                        <x-status-badge tone="info">Penugasan pola</x-status-badge>
                                    @endif
                                    @if ($viaOfficeHours)
                                        <span @class(['mt-1 block' => $assignments->isNotEmpty()])>
                                            <x-status-badge tone="neutral">{{ $employee->office_pattern_id ? 'Jam kantor' : 'Jam kantor (default)' }}</x-status-badge>
                                        </span>
                                    @endif
                                </td>
                                <td class="text-xs text-gray-600">
                                    @forelse ($assignments as $assignment)
                                        @php
                                            $covers = $assignment->coversDate($today);
                                            $upcoming = $assignment->start_date->greaterThan($today);
                                        @endphp
                                        <p @class(['mt-1' => ! $loop->first])>
                                            {{ $assignment->start_date->translatedFormat('d M Y') }} –
                                            {{ $assignment->end_date?->translatedFormat('d M Y') ?? 'tanpa batas' }}
                                            <span @class([
                                                'ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                                                'bg-emerald-50 text-emerald-700' => $covers,
                                                'bg-blue-50 text-blue-700' => ! $covers && $upcoming,
                                                'bg-gray-100 text-gray-500' => ! $covers && ! $upcoming,
                                            ])>{{ $covers ? 'Berlaku' : ($upcoming ? 'Akan datang' : 'Selesai') }}</span>
                                        </p>
                                    @empty
                                        <span class="text-gray-400">Tanpa penjadwalan — mengikuti pola jam kantor</span>
                                    @endforelse
                                </td>
                                <td><x-status-badge :tone="$employee->employment_status_tone">{{ $employee->employment_status_label }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">
                                @if ($hasNoScope)
                                    Anda belum punya cakupan data karyawan.
                                @else
                                    Belum ada karyawan yang memakai pola ini.
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $employees->links() }}</div>
        </section>
    </div>
</x-layouts.app>
