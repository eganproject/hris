<x-layouts.app title="Jadwal Saya - {{ config('app.name', 'HRIS') }}" heading="Jadwal Saya">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Self-service</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Jadwal Saya</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $employee->full_name }} @if ($employee->jobPosition)· {{ $employee->jobPosition->name }}@endif</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('my-roster.index', ['month' => $prevMonth]) }}" class="inline-flex size-9 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition hover:bg-gray-50" title="Bulan sebelumnya" aria-label="Bulan sebelumnya">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <span class="min-w-[9.5rem] text-center text-sm font-semibold text-gray-900">{{ $month->translatedFormat('F Y') }}</span>
                <a href="{{ route('my-roster.index', ['month' => $nextMonth]) }}" class="inline-flex size-9 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition hover:bg-gray-50" title="Bulan berikutnya" aria-label="Bulan berikutnya">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="{{ route('my-roster.index') }}" class="ml-1 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Bulan ini</a>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 sm:grid-cols-2">
            <x-stat-card label="Hari Kerja Bulan Ini" :value="$workDays" tone="emerald">
                <x-icon name="user-check" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Hari Libur Bulan Ini" :value="$offDays" tone="sky">
                <x-icon name="calendar-clock" class="size-5"/>
            </x-stat-card>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            @php
                $weekdayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                // Leading blanks so the 1st lands under the right weekday (week starts Monday).
                $leadBlanks = (int) $month->copy()->startOfMonth()->dayOfWeekIso - 1;
            @endphp

            <div class="grid grid-cols-7 gap-1 sm:gap-2">
                @foreach ($weekdayNames as $i => $wd)
                    <div @class(['pb-1 text-center text-[11px] font-semibold uppercase tracking-wide', 'text-rose-500' => $i === 6, 'text-gray-400' => $i !== 6])>{{ $wd }}</div>
                @endforeach

                @for ($b = 0; $b < $leadBlanks; $b++)
                    <div class="min-h-[76px] rounded-md"></div>
                @endfor

                @foreach ($days as $day)
                    @php
                        $key = $day->toDateString();
                        $holiday = $holidays[$key] ?? null;
                        $sched = $schedules[$key] ?? null;
                        $leave = $calendar->approvedOn($key);
                        // Pengajuan yang belum diputuskan: tidak mengubah mode kerja
                        // hari itu, hanya ditandai di bawahnya.
                        $pending = $calendar->pendingOn($key);
                        $isToday = $day->isSameDay($today);
                        $isSunday = (int) $day->dayOfWeekIso === 7;

                        $mode = \App\Support\WorkMode::for($sched, $leave);
                        $working = ! $holiday && $mode->isWorking;
                    @endphp
                    <div @class([
                        'min-h-[76px] rounded-md border p-1.5 transition sm:p-2',
                        'border-primary ring-1 ring-primary/30' => $isToday,
                        'border-gray-100 bg-gray-50/60' => ! $isToday,
                    ])>
                        <div class="flex items-center justify-between">
                            <span @class(['text-xs font-semibold', 'text-primary' => $isToday, 'text-rose-500' => $isSunday && ! $isToday, 'text-gray-700' => ! $isSunday && ! $isToday])>{{ $day->day }}</span>
                            @if ($isToday)<span class="rounded-full bg-primary px-1.5 text-[9px] font-semibold text-white">Hari ini</span>@endif
                        </div>

                        <div class="mt-1">
                            @if ($holiday)
                                <span class="block rounded bg-rose-50 px-1.5 py-1 text-[10px] font-medium leading-tight text-rose-600" title="{{ $holiday->name }}">{{ \Illuminate\Support\Str::limit($holiday->name, 22) }}</span>
                            @elseif ($working)
                                {{-- Hari kerja: tempatnya bisa kantor, rumah, atau luar kota --}}
                                <span @class([
                                    'block rounded px-1.5 py-1 text-[10px] font-semibold leading-tight',
                                    'bg-emerald-50 text-emerald-700' => ! $mode->isRemote(),
                                    'bg-indigo-100 text-indigo-700' => $mode->key === 'wfh',
                                    'bg-blue-100 text-blue-700' => $mode->key === 'trip',
                                ])>{{ $mode->isRemote() ? $mode->short.' '.$mode->label : $sched?->shift?->name }}</span>
                                @if ($sched?->shift)
                                    <span class="mt-0.5 block text-[10px] text-gray-500">{{ \Carbon\Carbon::parse($sched->shift->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($sched->shift->end_time)->format('H:i') }}</span>
                                @endif
                            @elseif ($mode->key === 'leave')
                                <span class="block rounded bg-amber-100 px-1.5 py-1 text-[10px] font-semibold leading-tight text-amber-800" title="{{ $mode->label }}">{{ \Illuminate\Support\Str::limit($mode->label, 16) }}</span>
                            @elseif ($sched)
                                <span class="block rounded bg-gray-100 px-1.5 py-1 text-[10px] font-medium leading-tight text-gray-500">Libur</span>
                            @else
                                <span class="block text-[10px] text-gray-300">—</span>
                            @endif

                            @if ($pending)
                                {{-- Garis putus-putus: sudah diajukan, belum berlaku. --}}
                                <span class="mt-0.5 block truncate rounded border border-dashed border-amber-400 px-1.5 py-0.5 text-[9px] font-semibold leading-tight text-amber-700"
                                      title="{{ $pending->leaveType?->name ?? 'Cuti/Izin' }} — {{ $pending->status->label() }}">
                                    Diajukan
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 pt-3 text-[11px] text-gray-500">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-100 ring-1 ring-emerald-300"></span> Masuk (shift)</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-indigo-100 ring-1 ring-indigo-300"></span> WFH</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-blue-100 ring-1 ring-blue-300"></span> Dinas luar</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber-100 ring-1 ring-amber-300"></span> Cuti/izin</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm border border-dashed border-amber-400"></span> Diajukan (menunggu)</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-gray-100 ring-1 ring-gray-300"></span> Libur</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-rose-100 ring-1 ring-rose-300"></span> Hari libur nasional</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-white ring-1 ring-primary"></span> Hari ini</span>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-950">Jadwal Masuk Terdekat</h2>
            <p class="mt-1 text-sm text-gray-500">7 hari kerja berikutnya berdasarkan roster Anda.</p>

            @if ($upcoming->isEmpty())
                <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500">
                    Belum ada jadwal masuk yang akan datang. Hubungi HR bila Anda belum memiliki pola jadwal.
                </div>
            @else
                <ul class="mt-4 divide-y divide-gray-100">
                    @foreach ($upcoming as $row)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            @php $rowMode = \App\Support\WorkMode::for($row, $calendar->approvedOn($row->work_date->toDateString())); @endphp
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ $row->work_date->translatedFormat('l, d F Y') }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $row->shift?->name ?? 'Shift' }}
                                    @if ($rowMode->isRemote())
                                        <span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $rowMode->chipClasses() }}">{{ $rowMode->short }} {{ $rowMode->label }}</span>
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                {{ \Carbon\Carbon::parse($row->shift->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($row->shift->end_time)->format('H:i') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>
