@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => ['badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'dot' => 'bg-emerald-500'],
        'warning' => ['badge' => 'bg-amber-50 text-amber-700 ring-amber-600/20', 'dot' => 'bg-amber-500'],
        'danger' => ['badge' => 'bg-red-50 text-red-700 ring-red-600/20', 'dot' => 'bg-red-500'],
        'info' => ['badge' => 'bg-blue-50 text-blue-700 ring-blue-600/20', 'dot' => 'bg-blue-500'],
        'neutral' => ['badge' => 'bg-gray-100 text-gray-700 ring-gray-500/20', 'dot' => 'bg-gray-400'],
    ];
    $style = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {$style['badge']}"]) }}>
    <span class="size-1.5 shrink-0 rounded-full {{ $style['dot'] }}" aria-hidden="true"></span>
    {{ $slot }}
</span>
