@props([
    'label',
    'value',
    'tone' => 'gray',
    'hint' => null,
    'href' => null,
    'active' => false,
])

@php
    $tones = [
        'gray' => 'bg-gray-100 text-gray-600',
        'primary' => 'bg-gray-900/[0.06] text-gray-900',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'violet' => 'bg-violet-50 text-violet-600',
    ];
    $iconClass = $tones[$tone] ?? $tones['gray'];

    $tag = $href ? 'a' : 'article';
    $border = $active ? 'border-primary ring-1 ring-primary/30' : 'border-gray-200';
    $interactive = $href
        ? 'cursor-pointer transition hover:border-gray-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2'
        : '';
    $classes = trim("block rounded-lg border {$border} bg-white p-5 shadow-sm {$interactive}");
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="{{ $classes }}">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm leading-snug text-gray-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold text-gray-950">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 truncate text-xs text-gray-400">{{ $hint }}</p>
            @endif
        </div>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $iconClass }}">
            {{ $slot }}
        </span>
    </div>
</{{ $tag }}>
