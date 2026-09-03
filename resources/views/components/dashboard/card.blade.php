{{-- Kerangka kartu dashboard: satu anatomi kepala untuk semua panel — chip ikon,
     judul, keterangan sebaris, dan aksi opsional di kanan. Dipakai hanya oleh
     Dashboard, jadi menyetelnya di sini tidak menyentuh halaman lain. --}}
@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'tone' => 'gray',
    // Badan tanpa padding, untuk isi yang mengatur paddingnya sendiri (daftar/tabel).
    'flush' => false,
])

@php
    $tones = [
        'gray' => 'bg-gray-100 text-gray-600 ring-gray-200',
        'primary' => 'bg-gray-900/[0.06] text-gray-900 ring-gray-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-200',
        'sky' => 'bg-sky-50 text-sky-600 ring-sky-200',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-200',
        'rose' => 'bg-rose-50 text-rose-600 ring-rose-200',
        'violet' => 'bg-violet-50 text-violet-600 ring-violet-200',
    ];
    $chip = $tones[$tone] ?? $tones['gray'];
@endphp

<section {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm']) }}>
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        @if ($icon)
            <span class="flex size-9 flex-none items-center justify-center rounded-md ring-1 ring-inset {{ $chip }}">
                <x-icon :name="$icon" class="size-[18px]"/>
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <h3 class="truncate text-sm font-semibold text-gray-950">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($action)
            <div class="flex-none">{{ $action }}</div>
        @endisset
    </div>

    <div class="flex-1 {{ $flush ? '' : 'p-5' }}">
        {{ $slot }}
    </div>
</section>
