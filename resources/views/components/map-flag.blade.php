@props(['color' => '#6b7280'])

{{-- Bendera penanda peta. Bentuknya sengaja sama persis dengan penanda di Leaflet
     (resources/js/attendance-map.js) supaya keterangan warna dan daftar di samping
     peta terbaca sebagai hal yang sama dengan titik di petanya. --}}
<svg {{ $attributes->merge(['class' => 'inline-block h-5 w-4 shrink-0']) }} viewBox="0 0 24 30" fill="none" aria-hidden="true">
    <path d="M4 28V2" stroke="#374151" stroke-width="2.5" stroke-linecap="round"/>
    <path d="M5.5 3.5h13.5l-3.2 4.75 3.2 4.75H5.5z" fill="{{ $color }}" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"/>
</svg>
