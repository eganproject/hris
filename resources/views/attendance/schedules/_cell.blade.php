{{--
    Satu sel roster (karyawan × tanggal). Dipakai oleh grid dan oleh respons AJAX
    override, supaya sel yang diperbarui tanpa reload persis sama dengan hasil
    render server.

    @var \App\Models\Employee $employee
    @var \Illuminate\Support\Carbon $day
    @var \App\Models\EmployeeSchedule|null $sched
    @var \App\Models\LeaveRequest|null $leave
--}}
@php
    $key = $day->toDateString();
    $isManual = $sched && $sched->source === \App\Enums\ScheduleSource::Manual;
    $isWfh = $sched && ! $sched->is_day_off && $sched->is_wfh;
    // Approved leave wins the cell: the shift may still be on the roster, but the
    // person will not be at work that day.
    $title = $leave
        ? ($leave->leaveType?->name ?? 'Cuti').' (disetujui)'.($sched && ! $sched->is_day_off ? ' — jadwal '.$sched->shift?->name : '')
        : ($sched && ! $sched->is_day_off ? $sched->shift?->name.($isWfh ? ' (WFH)' : '') : ($sched && $sched->is_day_off ? 'Libur' : 'Belum dijadwalkan')).($isManual ? ' (manual)' : '');
@endphp
<button type="button"
    @can('schedules.update') data-cell
        data-emp="{{ $employee->id }}" data-emp-name="{{ $employee->full_name }}"
        data-date="{{ $key }}" data-date-label="{{ $day->translatedFormat('l, d M Y') }}"
        data-shift="{{ $sched && ! $sched->is_day_off ? $sched->shift_id : '' }}"
        data-off="{{ $sched && $sched->is_day_off ? 1 : 0 }}"
        data-wfh="{{ $isWfh ? 1 : 0 }}"
        data-leave="{{ $leave ? ($leave->leaveType?->name ?? 'Cuti') : '' }}"
    @else disabled @endcan
    @class([
        'flex h-9 w-full items-center justify-center rounded text-[11px] font-semibold transition',
        'cursor-pointer hover:ring-2 hover:ring-primary/40' => auth()->user()->can('schedules.update'),
        'bg-amber-100 text-amber-800' => $leave,
        'bg-indigo-100 text-indigo-700' => ! $leave && $isWfh,
        'bg-primary/10 text-primary' => ! $leave && ! $isWfh && $sched && ! $sched->is_day_off,
        'text-gray-300' => ! $leave && (! $sched || $sched->is_day_off),
        'ring-1 ring-blue-400' => ! $leave && $isManual,
    ])
    title="{{ $title }}">
    @if ($leave)
        {{ $leave->leaveType?->code ?? 'C' }}
    @elseif ($sched && ! $sched->is_day_off)
        {{ $isWfh ? '🏠' : ($sched->shift?->code ?? '?') }}
    @elseif ($sched && $sched->is_day_off)
        —
    @else
        ·
    @endif
</button>
