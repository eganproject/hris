{{--
    Tujuh sel ringkasan di sebelah kanan sebuah kepala grup — dipakai oleh kepala nama,
    kepala merek, dan kepala model.

    Isinya selalu dihitung dari unit yang benar-benar termuat di grup itu, bukan dari
    kueri agregat tersendiri: satu sumber angka berarti kepala grup tidak mungkin
    bercerita berbeda dari baris yang muncul ketika ia dibuka.

    @param \Illuminate\Support\Collection<int, \App\Models\Asset> $units
--}}
@php
    // Satu nilai kalau seragam, jumlahnya kalau beragam. Menulis "3 lokasi" lebih jujur
    // daripada memilih salah satunya dan membuat pembaca mengira unit lainnya ada di
    // tempat yang sama.
    $ringkas = function ($values, string $noun): string {
        $unique = collect($values)->filter()->unique()->values();

        return match (true) {
            $unique->isEmpty() => '—',
            $unique->count() === 1 => (string) $unique->first(),
            default => $unique->count().' '.$noun,
        };
    };

    $dipegang = $units->filter(fn ($asset) => $asset->currentAssignment?->employee !== null)->count();
@endphp

<td class="text-sm text-gray-600">{{ $ringkas($units->map(fn ($asset) => $asset->category?->name), 'kategori') }}</td>
<td class="text-sm text-gray-600">{{ $ringkas($units->map(fn ($asset) => $asset->currentBranch?->name), 'lokasi') }}</td>
<td class="text-sm text-gray-600">{{ $ringkas($units->map(fn ($asset) => $asset->department?->name), 'divisi') }}</td>
<td>
    <div class="flex flex-wrap gap-1">
        @foreach ($units->groupBy(fn ($asset) => $asset->status_label) as $label => $rows)
            <x-status-badge :tone="$rows->first()->status_tone">{{ $label }} {{ $rows->count() }}</x-status-badge>
        @endforeach
    </div>
</td>
<td>
    <div class="flex flex-wrap gap-1">
        @foreach ($units->groupBy(fn ($asset) => $asset->condition_label) as $label => $rows)
            <x-status-badge :tone="$rows->first()->condition_tone">{{ $label }} {{ $rows->count() }}</x-status-badge>
        @endforeach
    </div>
</td>
<td class="text-sm text-gray-600">{{ $dipegang > 0 ? $dipegang.' dipegang' : '—' }}</td>
{{-- Spesifikasi yang seragam ditulis apa adanya; yang berbeda-beda hanya disebut
     jumlahnya, karena memilih salah satu akan membuat pembaca mengira unit lain
     spesifikasinya sama. --}}
<td class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($ringkas($units->map(fn ($asset) => $asset->specification), 'spesifikasi'), 120) }}</td>
