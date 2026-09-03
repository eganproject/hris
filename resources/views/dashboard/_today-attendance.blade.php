{{-- Jam masuk & jam pulang karyawan yang sedang login.

     $attendance datang dari DashboardController::currentAttendance(): baris absensi
     plus tanggal kerja yang memilikinya. Untuk shift lintas tengah malam tanggal itu
     bisa saja kemarin — dan itu memang shift yang sama, jadi labelnya ikut disebut. --}}
@php
    $row = $attendance['attendance'];
    $workDate = $attendance['work_date'];
    $isOvernight = ! $workDate->isSameDay(now());

    $slots = [
        ['label' => 'Absen Masuk', 'time' => $row?->clock_in, 'icon' => 'clock'],
        ['label' => 'Absen Pulang', 'time' => $row?->clock_out, 'icon' => 'clock'],
    ];
@endphp

<div class="grid grid-cols-2 gap-3">
    @foreach ($slots as $slot)
        <div @class([
            'rounded-md border px-3 py-2.5',
            'border-emerald-200 bg-emerald-50/60' => (bool) $slot['time'],
            'border-dashed border-gray-200 bg-gray-50/60' => ! $slot['time'],
        ])>
            <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                <x-icon :name="$slot['icon']" class="size-3.5"/>
                {{ $slot['label'] }}
            </p>
            <p @class([
                'mt-1 text-xl font-semibold tabular-nums',
                'text-emerald-700' => (bool) $slot['time'],
                'text-gray-400' => ! $slot['time'],
            ])>
                {{ $slot['time']?->format('H:i') ?? '--:--' }}
            </p>
            @if ($slot['time'] && ! $slot['time']->isSameDay($workDate))
                {{-- Jam pulang shift malam jatuh di tanggal berikutnya. --}}
                <p class="mt-0.5 text-[11px] text-gray-500">{{ $slot['time']->translatedFormat('d M') }}</p>
            @endif
        </div>
    @endforeach
</div>

<p class="mt-3 text-xs text-gray-500">
    @if ($row?->clock_out)
        Sudah absen masuk dan pulang.
    @elseif ($row?->clock_in)
        Sudah absen masuk, jam pulang belum tercatat.
    @else
        Jam masuk belum tercatat.
    @endif

    @if ($isOvernight)
        <span class="font-medium text-gray-600">Shift {{ $workDate->translatedFormat('d M Y') }} (lintas tengah malam).</span>
    @endif
</p>
