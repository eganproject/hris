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

        'payroll.index' => ['label' => 'Gaji / Payroll', 'parent' => 'dashboard'],

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
    <nav aria-label="Breadcrumb" class="mb-4">
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[12px] text-gray-500">
            @foreach ($items as $item)
                <li class="flex items-center gap-x-1.5">
                    @unless ($loop->first)
                        <svg class="size-3.5 shrink-0 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m9 6 6 6-6 6"></path>
                        </svg>
                    @endunless

                    @if ($loop->last)
                        <span class="font-medium text-gray-700" aria-current="page">{{ $item['label'] }}</span>
                    @else
                        <a href="{{ route($item['route']) }}" class="transition hover:text-gray-900">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
