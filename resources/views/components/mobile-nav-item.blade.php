@props(['item'])

{{-- Satu pintasan biasa di bilah navigasi bawah. Dipisah jadi komponen sendiri
     karena dipakai di sisi kiri dan kanan tombol tengah. --}}
@php $isActive = request()->routeIs(...$item['active']); @endphp

<li>
    <a href="{{ route($item['route']) }}"
       @class(['mobile-bottom-nav-link', 'is-active' => $isActive])
       @if ($isActive) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
        <span>{{ $item['label'] }}</span>
    </a>
</li>
