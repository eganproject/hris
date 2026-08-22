@php
    /**
     * Navigasi bawah untuk layar kecil. Isinya menyesuaikan hak akses: kandidat
     * diurutkan dari yang paling sering dipakai di ponsel — self-service dulu, karena
     * di ponsel yang dibuka biasanya absen dan cuti, bukan menu HR — lalu diambil
     * empat teratas yang boleh diakses. Slot kelima selalu "Lainnya", yang membuka
     * laci menu lengkap lewat penangan [data-mobile-nav-toggle] yang sudah ada.
     *
     * Empat, bukan lima: dengan tombol Lainnya jadi lima kolom, dan lebih dari itu
     * label akan terpotong di layar 360px.
     */
    $user = auth()->user();

    $candidates = [
        [
            'label' => 'Beranda',
            'route' => 'dashboard',
            'active' => ['dashboard'],
            'permission' => 'dashboard.view',
            'icon' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9.5 21v-6h5v6"></path>',
        ],
        [
            'label' => 'Absensi',
            'route' => 'my-attendance.index',
            'active' => ['my-attendance.*'],
            'permission' => 'my-attendance.view',
            'icon' => '<path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
        ],
        [
            'label' => 'Cuti',
            'route' => 'my-leave.index',
            'active' => ['my-leave.*'],
            'permission' => 'my-leave.view',
            'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M8 2v4M16 2v4M3 10h18"></path>',
        ],
        [
            'label' => 'Jadwal',
            'route' => 'my-roster.index',
            'active' => ['my-roster.*'],
            'permission' => null, // terbuka untuk semua akun yang login
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

    $items = collect($candidates)
        ->filter(fn (array $item) => $item['permission'] === null || $user?->can($item['permission']))
        ->take(4)
        ->values();
@endphp

@if ($items->isNotEmpty())
    <nav class="mobile-bottom-nav lg:hidden" aria-label="Navigasi utama">
        <ul class="mobile-bottom-nav-list">
            @foreach ($items as $item)
                @php $isActive = request()->routeIs(...$item['active']); @endphp
                <li>
                    <a href="{{ route($item['route']) }}"
                       @class(['mobile-bottom-nav-link', 'is-active' => $isActive])
                       @if ($isActive) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
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
