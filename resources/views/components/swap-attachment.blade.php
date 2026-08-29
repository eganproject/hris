@props(['swap'])

{{-- Bukti gambar pengajuan tukar jadwal. Berkasnya keluar lewat rute berotorisasi,
     jadi komponen ini aman dipakai di layar HR maupun di layar karyawan sendiri.
     Ditampilkan sebagai tautan, bukan thumbnail: baris-baris ini padat, dan gambar
     kecil di tiap baris justru membuat daftarnya sulit dipindai. --}}
@if ($swap->hasAttachment())
    <span {{ $attributes->merge(['class' => 'mt-1 inline-flex items-center gap-1 text-xs']) }}>
        <svg class="size-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-4.5-4.5L3 21"></path></svg>
        <a href="{{ route('swaps.attachment', $swap) }}" target="_blank" rel="noopener"
           class="max-w-[12rem] truncate font-medium text-primary hover:underline"
           title="Lihat bukti: {{ $swap->attachment_name }} ({{ $swap->attachmentSizeLabel() }})">{{ $swap->attachment_name }}</a>
        <a href="{{ route('swaps.attachment', [$swap, 'download' => 1]) }}"
           class="text-gray-400 hover:text-primary" title="Unduh bukti">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5M12 15V3"></path></svg>
        </a>
    </span>
@endif
