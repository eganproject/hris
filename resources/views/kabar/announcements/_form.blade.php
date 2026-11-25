@php $input = 'mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20'; @endphp
<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="md:col-span-2">
            <label for="title" class="block text-sm font-medium text-gray-700">Judul <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="title" name="title" value="{{ old('title', $announcement->title) }}" required maxlength="200" class="{{ $input }}" placeholder="mis. Libur & cuti bersama akhir tahun">
            @error('title')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="body" class="block text-sm font-medium text-gray-700">Isi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <textarea id="body" name="body" rows="8" required class="{{ $input }}">{{ old('body', $announcement->body) }}</textarea>
            @error('body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="audience" class="block text-sm font-medium text-gray-700">Penerima</label>
            <select id="audience" name="audience" class="{{ $input }}">
                <option value="all" @selected(old('audience', $announcement->audience) === 'all')>Semua karyawan</option>
                <option value="department" @selected(old('audience', $announcement->audience) === 'department')>Satu divisi</option>
            </select>
            @error('audience')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi (bila satu divisi)</label>
            <select id="department_id" name="department_id" class="{{ $input }}">
                <option value="">— Pilih divisi —</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $announcement->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="published_at" class="block text-sm font-medium text-gray-700">Waktu terbit</label>
            <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $announcement->published_at?->format('Y-m-d\TH:i')) }}" class="{{ $input }}">
            <p class="mt-2 text-xs text-gray-500">Kosongkan untuk terbit sekarang. Waktu di masa depan = terjadwal.</p>
            @error('published_at')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="attachment" class="block text-sm font-medium text-gray-700">Lampiran (PDF/gambar, maks {{ \App\Models\Announcement::ATTACHMENT_MAX_MB }} MB)</label>
            <input id="attachment" name="attachment" type="file" accept=".pdf,image/*" class="{{ $input }}">
            @if ($announcement->hasAttachment())
                <label class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="remove_attachment" value="1" class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                    Hapus lampiran saat ini ({{ $announcement->attachment_name }})
                </label>
            @endif
            @error('attachment')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                Sematkan di urutan teratas Kabar
            </label>
        </div>
    </div>
</section>
