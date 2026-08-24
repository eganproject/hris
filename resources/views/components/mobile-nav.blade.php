@php
    /**
     * Navigasi bawah untuk layar kecil.
     *
     * Absensi Saya diangkat jadi tombol bundar menonjol di tengah, karena itulah yang
     * paling sering ditekan dari ponsel — absen WFH, absen dinas luar — dan tombol
     * timbul lebih mudah dijangkau ibu jari daripada ikon kecil di deretan datar.
     *
     * Sisanya menyesuaikan hak akses: dua slot di kiri, dua di kanan, dengan slot
     * terakhir selalu "Lainnya" yang membuka laci menu lengkap lewat penangan
     * [data-mobile-nav-toggle] yang sudah ada.
     *
     * Bila akun itu tidak punya akses Absensi Saya (mis. petugas yang tidak tertaut
     * data karyawan), bilahnya kembali datar berisi lima pintasan biasa — tombol
     * tengah tidak dipaksakan ke menu yang bukan tujuan utamanya.
     */
    $user = auth()->user();

    // Semua menu self-service memerlukan akun yang tertaut ke data karyawan —
    // tanpa itu halamannya menjawab 403. Jadi keterkaitan itu ikut menentukan apa
    // yang muncul di bilah, bukan hak akses saja. (Sidebar sudah begitu untuk
    // "Jadwal Saya"; di sini dulu belum, sehingga tombolnya tampil lalu menolak.)
    $selfServiceEmployee = $user?->employee;

    $center = $selfServiceEmployee && $user?->can('my-attendance.view') ? [
        'label' => 'Absensi',
        'route' => 'my-attendance.index',
        'active' => ['my-attendance.*'],
        'icon' => '<circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.5 2.5 2.5 4.5-5"></path>',
    ] : null;

    $candidates = [
        [
            'label' => 'Beranda',
            'route' => 'dashboard',
            'active' => ['dashboard'],
            'permission' => 'dashboard.view',
            'icon' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9.5 21v-6h5v6"></path>',
        ],
        [
            'label' => 'Cuti',
            'route' => 'my-leave.index',
            'active' => ['my-leave.*'],
            'permission' => 'my-leave.view',
            'needsEmployee' => true,
            'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M8 2v4M16 2v4M3 10h18"></path>',
        ],
        [
            'label' => 'Jadwal',
            'route' => 'my-roster.index',
            'active' => ['my-roster.*'],
            'permission' => null, // tanpa hak akses khusus, tapi tetap butuh data karyawan
            'needsEmployee' => true,
            'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M8 4v3M16 4v3"></path>',
        ],
        [
            'label' => 'Harian',
            'route' => 'attendance.daily.index',
            'active' => ['attendance.daily.*'],
            'permission' => 'attendance-daily.view',
            'icon' => '<path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
        ],
        [
            'label' => 'Karyawan',
            'route' => 'employees.index',
            'active' => ['employees.*'],
            'permission' => 'employees.view',
            'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>',
        ],
    ];

    // Dengan tombol tengah: 3 pintasan + Lainnya, dibagi 2 kiri & 2 kanan.
    // Tanpa tombol tengah: 4 pintasan + Lainnya dalam satu deret datar.
    $sides = collect($candidates)
        ->filter(fn (array $item) => ($item['permission'] === null || $user?->can($item['permission']))
            && (! ($item['needsEmployee'] ?? false) || $selfServiceEmployee))
        ->take($center ? 3 : 4)
        ->values();

    $left = $center ? $sides->take(2) : $sides;
    $right = $center ? $sides->slice(2) : collect();
@endphp

@if ($sides->isNotEmpty() || $center)
    <nav @class(['mobile-bottom-nav', 'lg:hidden', 'has-center' => (bool) $center]) aria-label="Navigasi utama">
        <ul class="mobile-bottom-nav-list">
            @foreach ($left as $item)
                <x-mobile-nav-item :item="$item" />
            @endforeach

            @if ($center)
                @php $centerActive = request()->routeIs(...$center['active']); @endphp
                <li class="mobile-bottom-nav-center">
                    <a href="{{ route($center['route']) }}"
                       @class(['mobile-bottom-nav-fab-link', 'is-active' => $centerActive])
                       @if ($centerActive) aria-current="page" @endif
                       aria-label="Absensi Saya">
                        <span class="mobile-bottom-nav-fab">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $center['icon'] !!}</svg>
                        </span>
                        <span class="mobile-bottom-nav-fab-label">{{ $center['label'] }}</span>
                    </a>
                </li>
            @endif

            @foreach ($right as $item)
                <x-mobile-nav-item :item="$item" />
            @endforeach

            <li>
                <button type="button" data-mobile-nav-toggle class="mobile-bottom-nav-link" aria-label="Buka menu lengkap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5" cy="12" r="1.6"></circle><circle cx="12" cy="12" r="1.6"></circle><circle cx="19" cy="12" r="1.6"></circle></svg>
                    <span>Lainnya</span>
                </button>
            </li>
        </ul>
    </nav>
@endif
