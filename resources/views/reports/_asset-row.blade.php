{{--
    Satu baris unit aset di Register Aset.

    Dipakai oleh kedua tampilan — daftar rinci dan isi grup yang dibuka — supaya
    keduanya tidak mungkin lama-lama menampilkan kolom yang berbeda untuk aset yang
    sama. $nested menandai baris yang berada di dalam sebuah grup: namanya sudah
    tertulis di kepala grup, jadi tidak diulang, dan barisnya ditakik ke dalam.

    Barisnya tetap <tr> polos di tabel yang sama, bukan tabel bersarang di dalam satu
    sel: hanya dengan begitu kolom unit benar-benar lurus di bawah kolom grupnya.

    @param \App\Models\Asset $asset
    @param bool $nested
    @param string|null $groupId  id grup yang menyembunyikan/menampilkan baris ini
--}}
@php($nested = $nested ?? false)
@php($groupId = $groupId ?? null)

<tr @class(['bg-gray-50/60' => $nested, 'hidden' => $groupId !== null]) @if ($groupId) data-group-body="{{ $groupId }}" @endif>
    <td @class(['pl-10' => $nested])>
        <a href="{{ route('assets.show', $asset) }}" class="font-mono text-xs font-medium text-gray-950 hover:text-primary hover:underline">{{ $asset->asset_code }}</a>
        @if ($nested)
            @if ($asset->serial_number)
                <p class="mt-0.5 text-xs text-gray-400">SN {{ $asset->serial_number }}</p>
            @endif
        @else
            <p class="mt-0.5 text-xs text-gray-500">{{ $asset->name }}</p>
        @endif
    </td>
    <td class="text-sm text-gray-600">{{ $asset->category?->name ?? '—' }}</td>
    <td class="text-sm text-gray-600">
        {{ $asset->currentBranch?->name ?? '—' }}
        @if ($asset->owning_branch_id !== $asset->current_branch_id)
            <span class="block text-xs text-gray-400">milik {{ $asset->owningBranch?->name ?? '—' }}</span>
        @endif
    </td>
    <td class="text-sm text-gray-600">{{ $asset->department?->name ?? '—' }}</td>
    <td><x-status-badge :tone="$asset->status_tone">{{ $asset->status_label }}</x-status-badge></td>
    <td><x-status-badge :tone="$asset->condition_tone">{{ $asset->condition_label }}</x-status-badge></td>
    <td class="text-sm text-gray-600">
        @if ($asset->currentAssignment?->employee)
            {{ $asset->currentAssignment->employee->full_name }}
            <span class="block text-xs text-gray-400">sejak {{ $asset->currentAssignment->assigned_at?->translatedFormat('d M Y') ?? '—' }}</span>
        @elseif ($asset->status?->isSharedUse())
            {{-- Bukan "—" seperti aset menganggur: kosongnya di sini disengaja, dan
                 pembaca perlu tahu bedanya sebelum menyimpulkan barangnya luput
                 dicatat serah terimanya. --}}
            <span class="text-violet-700">Pakai bersama</span>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </td>
    <td class="text-right text-sm text-gray-700">{{ $asset->acquisition_cost === null ? '—' : 'Rp '.number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
</tr>
