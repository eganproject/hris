@php
    // Route name => ['label' => display text, 'parent' => parent route name or null].
    // Breadcrumbs are built by walking the parent chain up from the current route.
    // Parent routes are always parameter-less "index" routes, so route() needs no args.
    $trails = [
        'dashboard' => ['label' => 'Dashboard', 'parent' => null],

        'employees.index' => ['label' => 'Data Karyawan', 'parent' => 'dashboard'],
        'employees.create' => ['label' => 'Tambah Karyawan', 'parent' => 'employees.index'],
        'employees.edit' => ['label' => 'Edit Karyawan', 'parent' => 'employees.index'],
        'employees.show' => ['label' => 'Detail Karyawan', 'parent' => 'employees.index'],

        'organization.index' => ['label' => 'Organization', 'parent' => 'dashboard'],
        'organization.branches.index' => ['label' => 'Lokasi Kerja', 'parent' => 'organization.index'],
        'organization.branches.create' => ['label' => 'Tambah Lokasi', 'parent' => 'organization.branches.index'],
        'organization.branches.edit' => ['label' => 'Edit Lokasi', 'parent' => 'organization.branches.index'],
        'organization.departments.index' => ['label' => 'Divisi', 'parent' => 'organization.index'],
        'organization.departments.create' => ['label' => 'Tambah Divisi', 'parent' => 'organization.departments.index'],
        'organization.departments.edit' => ['label' => 'Edit Divisi', 'parent' => 'organization.departments.index'],
        'organization.job-positions.index' => ['label' => 'Jabatan', 'parent' => 'organization.index'],
        'organization.job-positions.create' => ['label' => 'Tambah Jabatan', 'parent' => 'organization.job-positions.index'],
        'organization.job-positions.edit' => ['label' => 'Edit Jabatan', 'parent' => 'organization.job-positions.index'],

        'attendance.shifts.index' => ['label' => 'Shift Kerja', 'parent' => 'dashboard'],
        'attendance.shifts.create' => ['label' => 'Tambah Shift', 'parent' => 'attendance.shifts.index'],
        'attendance.shifts.edit' => ['label' => 'Edit Shift', 'parent' => 'attendance.shifts.index'],

        'attendance.holidays.index' => ['label' => 'Hari Libur', 'parent' => 'dashboard'],
        'attendance.holidays.create' => ['label' => 'Tambah Libur', 'parent' => 'attendance.holidays.index'],
        'attendance.holidays.edit' => ['label' => 'Edit Libur', 'parent' => 'attendance.holidays.index'],

        'attendance.daily.index' => ['label' => 'Absensi Harian', 'parent' => 'dashboard'],

        'attendance.devices.index' => ['label' => 'Perangkat Absensi', 'parent' => 'dashboard'],
        'attendance.devices.monitor' => ['label' => 'Monitor Mesin', 'parent' => 'attendance.devices.index'],
        'attendance.devices.create' => ['label' => 'Tambah Perangkat', 'parent' => 'attendance.devices.index'],
        'attendance.devices.edit' => ['label' => 'Edit Perangkat', 'parent' => 'attendance.devices.index'],
        'attendance.punches.index' => ['label' => 'Log Punch', 'parent' => 'attendance.devices.index'],

        'attendance.schedule-patterns.index' => ['label' => 'Pola Jadwal', 'parent' => 'dashboard'],
        'attendance.schedule-patterns.create' => ['label' => 'Tambah Pola', 'parent' => 'attendance.schedule-patterns.index'],
        'attendance.schedule-patterns.edit' => ['label' => 'Edit Pola', 'parent' => 'attendance.schedule-patterns.index'],

        'attendance.schedules.index' => ['label' => 'Jadwal Kerja', 'parent' => 'dashboard'],
        'attendance.schedules.assign' => ['label' => 'Tugaskan Pola', 'parent' => 'attendance.schedules.index'],

        'attendance.leave.index' => ['label' => 'Cuti & Izin', 'parent' => 'dashboard'],
        'attendance.leave.create' => ['label' => 'Ajukan Cuti/Izin', 'parent' => 'attendance.leave.index'],

        'my-leave.index' => ['label' => 'Cuti Saya', 'parent' => 'dashboard'],
        'my-leave.create' => ['label' => 'Ajukan Cuti/Izin', 'parent' => 'my-leave.index'],

        'access-control.index' => ['label' => 'Pengaturan Akses', 'parent' => 'dashboard'],
    ];

    $current = request()->route()?->getName();
    $items = [];
    $cursor = $current;

    while ($cursor && isset($trails[$cursor])) {
        $items[] = ['route' => $cursor, 'label' => $trails[$cursor]['label']];
        $cursor = $trails[$cursor]['parent'] ?? null;
    }

    $items = array_reverse($items);
@endphp

@if (count($items) > 1)
    <nav aria-label="Breadcrumb" class="mt-0.5 hidden overflow-hidden sm:block">
        <ol class="flex flex-nowrap items-center gap-1 whitespace-nowrap text-[11px] leading-none text-gray-400">
            @foreach ($items as $item)
                <li class="flex min-w-0 items-center gap-1">
                    @unless ($loop->first)
                        <svg class="size-2.5 shrink-0 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m9 6 6 6-6 6"></path>
                        </svg>
                    @endunless

                    @if ($loop->last)
                        <span class="truncate font-medium text-gray-600" aria-current="page">{{ $item['label'] }}</span>
                    @else
                        <a href="{{ route($item['route']) }}" class="shrink-0 transition hover:text-primary">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@else
    <p class="hidden text-[11px] text-gray-500 sm:block">Administration workspace</p>
@endif
