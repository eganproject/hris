@props(['request'])

{{-- Tautan lampiran pengajuan cuti. Berkasnya keluar lewat rute berotorisasi, jadi
     komponen ini aman dipakai di layar HR maupun di layar karyawan sendiri. --}}
@if ($request->hasAttachment())
    <span {{ $attributes->merge(['class' => 'mt-1 inline-flex items-center gap-1 text-xs']) }}>
        <svg class="size-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
        <a href="{{ route('leave.attachment', $request) }}" target="_blank" rel="noopener"
           class="max-w-[12rem] truncate font-medium text-primary hover:underline"
           title="Lihat lampiran: {{ $request->attachment_name }} ({{ $request->attachmentSizeLabel() }})">{{ $request->attachment_name }}</a>
        <a href="{{ route('leave.attachment', [$request, 'download' => 1]) }}"
           class="text-gray-400 hover:text-primary" title="Unduh lampiran">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5M12 15V3"></path></svg>
        </a>
    </span>
@endif
