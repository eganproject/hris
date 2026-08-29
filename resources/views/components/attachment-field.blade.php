@props([
    'name' => 'attachment',
    'label' => 'Lampiran',
    'maxMb' => 2,
    // Jenis berkas yang diterima. Defaultnya gambar + PDF (lampiran cuti); pemakai
    // lain — mis. dokumen kontrak — mempersempitnya lewat props ini.
    'accept' => '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf',
    'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    'typeError' => 'Lampiran harus berupa gambar (JPG, PNG, WEBP) atau PDF.',
    'hint' => null,
    'required' => false,
    'wrapperClass' => 'sm:col-span-2',
])

@php
    $id = $name.'-field';
    $hint = $hint ?? 'Opsional. Gambar (JPG, PNG, WEBP) atau PDF, maksimal '.$maxMb.' MB. Misalnya surat keterangan sakit atau surat tugas.';
@endphp

{{-- Input lampiran dengan pratinjau langsung. Gambar ditampilkan sebagai thumbnail;
     PDF ditampilkan sebagai kartu berisi nama & ukuran plus tautan buka di tab baru,
     karena penampil PDF di dalam iframe tidak tersedia di semua browser seluler dan
     akan menyisakan kotak kosong. Batas ukuran & jenis juga diperiksa di sini supaya
     kesalahan ketahuan sebelum formulir terkirim. --}}
<div class="{{ $wrapperClass }}" data-attachment-field data-max-mb="{{ $maxMb }}"
    data-mimes="{{ implode(',', $mimes) }}" data-type-error="{{ $typeError }}">
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">{{ $label }}
        @if ($required)<span class="field-requirement is-required">*</span>@endif
    </label>

    <input id="{{ $id }}" name="{{ $name }}" type="file" data-attachment-input
        accept="{{ $accept }}" @required($required)
        class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">

    <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>

    @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    <p data-attachment-error class="mt-1 hidden text-sm text-red-600"></p>

    <div data-attachment-preview class="mt-3 hidden items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
        <a data-attachment-open target="_blank" rel="noopener" class="flex-none">
            <img data-attachment-image alt="Pratinjau lampiran" class="hidden size-20 rounded object-cover ring-1 ring-gray-200">
            <span data-attachment-doc class="hidden size-20 items-center justify-center rounded bg-red-50 ring-1 ring-red-200">
                <svg class="size-8 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path></svg>
            </span>
        </a>
        <div class="min-w-0 flex-1">
            <p data-attachment-name class="truncate text-sm font-medium text-gray-900"></p>
            <p data-attachment-size class="mt-0.5 text-xs text-gray-500"></p>
            <div class="mt-1.5 flex items-center gap-3 text-xs">
                <a data-attachment-open target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Buka di tab baru</a>
                <button type="button" data-attachment-clear class="text-red-600 hover:underline">Hapus</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        (function () {
            const FALLBACK_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

            document.querySelectorAll('[data-attachment-field]').forEach((field) => {
                const input = field.querySelector('[data-attachment-input]');
                const preview = field.querySelector('[data-attachment-preview]');
                const image = field.querySelector('[data-attachment-image]');
                const doc = field.querySelector('[data-attachment-doc]');
                const nameEl = field.querySelector('[data-attachment-name]');
                const sizeEl = field.querySelector('[data-attachment-size]');
                const errorEl = field.querySelector('[data-attachment-error]');
                const links = field.querySelectorAll('[data-attachment-open]');
                const maxBytes = Number(field.dataset.maxMb || 2) * 1024 * 1024;
                // Tiap field membawa daftar jenisnya sendiri: lampiran cuti menerima
                // gambar & PDF, dokumen kontrak hanya PDF.
                const allowed = (field.dataset.mimes || '').split(',').filter(Boolean);
                const mimes = allowed.length ? allowed : FALLBACK_MIME;
                const typeError = field.dataset.typeError || 'Jenis berkas tidak didukung.';

                let objectUrl = null;

                const release = () => {
                    if (objectUrl) URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                };

                const reset = () => {
                    release();
                    preview.classList.add('hidden');
                    preview.classList.remove('flex');
                    image.classList.add('hidden');
                    doc.classList.add('hidden');
                    doc.classList.remove('flex');
                    image.removeAttribute('src');
                };

                const fail = (message) => {
                    input.value = '';
                    reset();
                    errorEl.textContent = message;
                    errorEl.classList.remove('hidden');
                };

                const humanSize = (bytes) => bytes >= 1048576
                    ? (bytes / 1048576).toFixed(1) + ' MB'
                    : Math.max(1, Math.round(bytes / 1024)) + ' KB';

                input.addEventListener('change', () => {
                    errorEl.classList.add('hidden');
                    const file = input.files?.[0];

                    if (!file) {
                        reset();
                        return;
                    }

                    // Dicegat di sini supaya penggunanya tahu sebelum menunggu unggahan
                    // selesai lalu ditolak server.
                    if (!mimes.includes(file.type)) {
                        fail(typeError);
                        return;
                    }

                    if (file.size > maxBytes) {
                        fail('Ukuran berkas ' + humanSize(file.size) + ', melebihi batas ' + field.dataset.maxMb + ' MB.');
                        return;
                    }

                    release();
                    objectUrl = URL.createObjectURL(file);

                    nameEl.textContent = file.name;
                    sizeEl.textContent = humanSize(file.size) + ' · ' + (file.type === 'application/pdf' ? 'PDF' : 'Gambar');
                    links.forEach((link) => { link.href = objectUrl; });

                    if (file.type === 'application/pdf') {
                        doc.classList.remove('hidden');
                        doc.classList.add('flex');
                        image.classList.add('hidden');
                    } else {
                        image.src = objectUrl;
                        image.classList.remove('hidden');
                        doc.classList.add('hidden');
                        doc.classList.remove('flex');
                    }

                    preview.classList.remove('hidden');
                    preview.classList.add('flex');
                });

                field.querySelector('[data-attachment-clear]')?.addEventListener('click', () => {
                    input.value = '';
                    errorEl.classList.add('hidden');
                    reset();
                });

                // Blob URL dilepas saat halaman ditinggalkan agar tidak menahan memori.
                window.addEventListener('pagehide', release);
            });
        })();
    </script>
    @endpush
@endonce
