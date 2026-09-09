{{--
    Satu baris unit aset di Register Aset.

    Dipakai oleh ketiga bentuk baris supaya tidak mungkin lama-lama menampilkan kolom
    yang berbeda untuk aset yang sama:

      flat   — daftar rinci per unit. Kode aset di depan, namanya di bawahnya.
      child  — unit di dalam grup yang dibuka. Namanya sudah tertulis di kepala grup,
               jadi tidak diulang; barisnya ditakik dan tersembunyi sampai dibuka.
      single — nama yang cuma punya satu unit. Tidak ada yang perlu dibuka, jadi ia
               langsung tampil sebagai barisnya sendiri, dengan nama di depan supaya
               sejajar dibaca dengan kepala grup di atas dan di bawahnya.

    Barisnya tetap <tr> polos di tabel yang sama, bukan tabel bersarang di dalam satu
    sel: hanya dengan begitu kolom unit benar-benar lurus di bawah kolom grupnya.

    @param \App\Models\Asset $asset
    @param string $variant  flat|child|single
    @param string|null $groupId  id grup yang menyembunyikan/menampilkan baris ini
--}}
@php($variant = $variant ?? 'flat')
@php($groupId = $groupId ?? null)

{{-- Merek dan model sering kosong, dan salah satunya bisa terisi sendirian. Dirangkai
     dari yang benar-benar ada supaya tidak pernah muncul spasi menggantung atau tanda
     hubung yang menunggu pasangan yang tidak datang.

     Ditulis sebagai @php(...) sebaris, bukan blok @php ... @endphp: Blade mencari
     pembuka blok dari kemunculan "@php" yang PERTAMA di berkas, sehingga dua baris
     di atas ikut tertelan ke dalam blok yang sama dan hasil kompilasinya rusak. --}}
@php($merekModel = collect([$asset->brand, $asset->model])->filter()->implode(' '))

{{-- Baris 'single' berdiri sejajar dengan kepala grup, jadi ia memakai garis
     pemisah tebal yang sama supaya tingkatannya terbaca sama. --}}
<tr @class([
    'bg-gray-50/60' => $variant === 'child',
    'border-t-2 border-gray-200' => $variant === 'single',
    'hidden' => $groupId !== null,
]) @if ($groupId) data-group-body="{{ $groupId }}" @endif>
    {{-- pl-10 menakik unit ke dalam grupnya; pl-6 hanya menggantikan lebar chevron,
         supaya nama yang berdiri sendiri tetap lurus dengan nama grup di atasnya. --}}
    <td @class(['pl-10' => $variant === 'child', 'pl-6' => $variant === 'single'])>
        @if ($variant === 'flat')
            <a href="{{ route('assets.show', $asset) }}" class="font-mono text-xs font-medium text-gray-950 hover:text-primary hover:underline">{{ $asset->asset_code }}</a>
            <p class="mt-0.5 text-xs text-gray-500">{{ $asset->name }}</p>
        @else
            {{-- Baris unit di tampilan ringkas: yang dicari mata lebih dulu adalah
                 barangnya — nama untuk yang berdiri sendiri, merek dan model untuk yang
                 berada di dalam grup (namanya sudah tertulis di kepala grup). Kode aset
                 turun jadi keterangan kecil; ia identitas untuk dicocokkan dengan label
                 di fisik barang, bukan yang dibaca lebih dulu.

                 Kalau merek dan model dua-duanya kosong, kode aset naik menjadi baris
                 pertama. Menyisakan baris kosong di atasnya hanya akan membuat baris itu
                 terlihat rusak, padahal datanya memang belum diisi. --}}
            @if ($variant === 'single')
                <span class="font-semibold text-gray-950">{{ $asset->name }}</span>
            @endif

            @if (filled($merekModel))
                <p @class(['text-gray-800', 'mt-0.5 text-xs text-gray-500' => $variant === 'single', 'text-sm font-medium' => $variant === 'child'])>{{ $merekModel }}</p>
            @endif

            <p @class(['mt-0.5' => $variant === 'single' || filled($merekModel)])>
                <a href="{{ route('assets.show', $asset) }}" @class([
                    'font-mono text-xs hover:text-primary hover:underline',
                    'text-gray-500' => filled($merekModel) || $variant === 'single',
                    'font-medium text-gray-950' => ! filled($merekModel) && $variant === 'child',
                ])>{{ $asset->asset_code }}</a>

                @if ($asset->serial_number)
                    <span class="text-xs text-gray-400">&middot; SN {{ $asset->serial_number }}</span>
                @endif
            </p>
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
    {{-- Spesifikasi boleh sampai 2000 karakter dan sering berbaris-baris. Dipotong
         supaya tidak ada satu baris pun yang meregangkan seluruh tabel, dan teks
         utuhnya tetap terbaca lewat title saat kursor berhenti di atasnya. --}}
    <td class="text-sm text-gray-600">
        @if (filled($asset->specification))
            <span title="{{ $asset->specification }}">{{ \Illuminate\Support\Str::limit($asset->specification, 120) }}</span>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </td>
</tr>
