{{--
    Satu sel roster (karyawan × tanggal). Dipakai oleh grid dan oleh respons AJAX
    override, supaya sel yang diperbarui tanpa reload persis sama dengan hasil
    render server.

    Mode kerjanya ditentukan App\Support\WorkMode, yang mengikuti urutan prioritas
    AttendanceResolver — jadi hari WFH tampil sebagai hari kerja, baik WFH-nya
    berasal dari roster maupun dari pengajuan yang disetujui.

    @var \App\Models\Employee $employee
    @var \Illuminate\Support\Carbon $day
    @var \App\Models\EmployeeSchedule|null $sched
    @var \App\Models\LeaveRequest|null $leave
--}}
@php
    $key = $day->toDateString();
    $mode = \App\Support\WorkMode::for($sched, $leave);
    $isManual = $sched && $sched->source === \App\Enums\ScheduleSource::Manual;

    // Sumber-nya penting bagi HR: yang berasal dari pengajuan tidak bisa disunting
    // dari sel ini, harus lewat menu Cuti & Izin.
    $title = $mode->describe($sched)
        .($leave ? ' (disetujui)' : '')
        .($isManual ? ' (manual)' : '');
@endphp
<button type="button"
    @can('schedules.update') data-cell
        data-emp="{{ $employee->id }}" data-emp-name="{{ $employee->full_name }}"
        data-date="{{ $key }}" data-date-label="{{ $day->translatedFormat('l, d M Y') }}"
        data-shift="{{ $sched && ! $sched->is_day_off ? $sched->shift_id : '' }}"
        data-off="{{ $sched && $sched->is_day_off ? 1 : 0 }}"
        data-wfh="{{ $sched && ! $sched->is_day_off && $sched->is_wfh ? 1 : 0 }}"
        data-leave="{{ $leave ? ($leave->leaveType?->name ?? 'Cuti') : '' }}"
    @else disabled @endcan
    @class([
        'flex h-9 w-full items-center justify-center rounded text-[11px] font-semibold transition',
        'cursor-pointer hover:ring-2 hover:ring-primary/40' => auth()->user()->can('schedules.update'),
        $mode->chipClasses(),
        // Garis tepi menandai asal-usulnya: biru = override manual, kuning = dari
        // pengajuan yang disetujui (tidak bisa disunting dari sini).
        'ring-1 ring-amber-400' => (bool) $leave,
        'ring-1 ring-blue-400' => ! $leave && $isManual,
    ])
    title="{{ $title }}">
    {{ $mode->short }}
</button>
