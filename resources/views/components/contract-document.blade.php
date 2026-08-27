@props(['contract'])

{{-- Tautan dokumen kontrak. Berkasnya keluar lewat rute berotorisasi, jadi komponen
     ini aman dipakai di layar HR maupun di layar karyawan sendiri. --}}
@if ($contract->hasDocument())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs']) }}>
        <svg class="size-3.5 shrink-0 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path></svg>
        <a href="{{ route('employees.contracts.document', $contract) }}" target="_blank" rel="noopener"
           class="max-w-[12rem] truncate font-medium text-primary hover:underline"
           title="Lihat dokumen kontrak: {{ $contract->document_name }} ({{ $contract->documentSizeLabel() }})">{{ $contract->document_name }}</a>
        <a href="{{ route('employees.contracts.document', [$contract, 'download' => 1]) }}"
           class="text-gray-400 hover:text-primary" title="Unduh dokumen kontrak">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5M12 15V3"></path></svg>
        </a>
    </span>
@else
    <span {{ $attributes->merge(['class' => 'text-xs text-gray-400']) }}>—</span>
@endif
