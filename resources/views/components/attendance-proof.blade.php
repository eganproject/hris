@props(['attendance'])

{{-- Bukti absen mandiri: thumbnail selfie masuk/pulang, masing-masing menaut ke
     foto ukuran penuh dan ke titik koordinatnya di peta. --}}
@php
    $proofs = array_filter([
        'Masuk' => $attendance->selfieFor('in'),
        'Pulang' => $attendance->selfieFor('out'),
    ]);
@endphp

@if ($proofs)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}>
        @foreach ($proofs as $label => $proof)
            @php
                $coords = ($proof['latitude'] !== null && $proof['longitude'] !== null)
                    ? number_format($proof['latitude'], 5).', '.number_format($proof['longitude'], 5)
                        .($proof['accuracy'] !== null ? ' (±'.$proof['accuracy'].' m)' : '')
                    : 'tanpa koordinat';
            @endphp
            <a href="{{ $proof['photo_url'] }}" target="_blank" rel="noopener"
               title="Selfie absen {{ strtolower($label) }} — {{ $coords }}">
                <img src="{{ $proof['photo_url'] }}" alt="Selfie absen {{ strtolower($label) }}"
                     class="size-8 rounded object-cover ring-1 ring-gray-200 transition hover:ring-primary">
            </a>
            @if ($proof['map_url'])
                <a href="{{ $proof['map_url'] }}" target="_blank" rel="noopener"
                   title="Lokasi absen {{ strtolower($label) }} — {{ $coords }}"
                   class="text-gray-400 transition hover:text-primary">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </a>
            @endif
        @endforeach
    </div>
@else
    <span class="text-sm text-gray-300">—</span>
@endif
