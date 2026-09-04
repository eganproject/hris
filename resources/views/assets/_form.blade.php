@php
    // Status yang lahir dari alur kerja (Dipegang, Dilepas) tidak boleh diketik ulang
    // lewat formulir master — ia ditampilkan sebagai keterangan saja, dan controller
    // mempertahankan nilainya. Lihat AssetRequest::payload().
    $statusIsEditable = $asset->status === null || in_array($asset->status, \App\Enums\AssetStatus::MANUAL, true);
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-900">Identitas</h2>
    <p class="mt-1 text-xs text-gray-500">Kode aset dibuat otomatis dari kategori dan lokasi pemilik saat disimpan, lalu tidak berubah lagi.</p>

    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="category_id" class="block text-sm font-medium text-gray-700">Kategori <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="category_id" name="category_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Pilih kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $asset->category_id) === (string) $category->id)>{{ $category->name }}@if ($category->requires_serial) (nomor seri wajib)@endif</option>
                @endforeach
            </select>
            @error('category_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Aset <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $asset->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Laptop Dell Latitude 5420">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="brand" class="block text-sm font-medium text-gray-700">Merek</label>
            <input id="brand" name="brand" value="{{ old('brand', $asset->brand) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('brand')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="model" class="block text-sm font-medium text-gray-700">Model / Tipe</label>
            <input id="model" name="model" value="{{ old('model', $asset->model) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('model')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="serial_number" class="block text-sm font-medium text-gray-700">Nomor Seri</label>
            <input id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            <p class="mt-1 text-xs text-gray-500">Wajib untuk kategori yang menandainya. Harus unik di seluruh daftar aset.</p>
            @error('serial_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="condition" class="block text-sm font-medium text-gray-700">Kondisi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="condition" name="condition" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @foreach ($conditions as $value => $label)
                    <option value="{{ $value }}" @selected(old('condition', $asset->condition?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('condition')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="specification" class="block text-sm font-medium text-gray-700">Spesifikasi</label>
            <textarea id="specification" name="specification" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Core i5, RAM 16 GB, SSD 512 GB">{{ old('specification', $asset->specification) }}</textarea>
            @error('specification')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-900">Kepemilikan &amp; Lokasi</h2>
    <p class="mt-1 text-xs text-gray-500">Lokasi pemilik menentukan kode aset dan tidak ikut berubah saat barangnya dipindah. Lokasi sekarang adalah tempat barangnya berada hari ini.</p>

    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="owning_branch_id" class="block text-sm font-medium text-gray-700">Lokasi Pemilik <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="owning_branch_id" name="owning_branch_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Pilih lokasi</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('owning_branch_id', $asset->owning_branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('owning_branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="current_branch_id" class="block text-sm font-medium text-gray-700">Lokasi Sekarang <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="current_branch_id" name="current_branch_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Pilih lokasi</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('current_branch_id', $asset->current_branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('current_branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi Pemilik <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="department_id" name="department_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Pilih divisi</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $asset->department_id) === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="secondary_department_id" class="block text-sm font-medium text-gray-700">Divisi Kedua</label>
            <select id="secondary_department_id" name="secondary_department_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tidak ada</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('secondary_department_id', $secondaryDepartmentId) === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Opsional. Untuk aset yang dimiliki bersama dua divisi — keduanya akan melihat aset ini.</p>
            @error('secondary_department_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">Status <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            @if ($statusIsEditable)
                <select id="status" name="status" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $asset->status?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <div class="mt-2 flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <x-status-badge :tone="$asset->status_tone">{{ $asset->status_label }}</x-status-badge>
                    <span class="text-xs text-gray-500">Diatur oleh alur kerja, tidak bisa diubah dari sini.</span>
                </div>
            @endif
            @error('status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-900">Perolehan &amp; Garansi</h2>

    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-3">
        <div>
            <label for="acquired_at" class="block text-sm font-medium text-gray-700">Tanggal Perolehan</label>
            <input id="acquired_at" name="acquired_at" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ old('acquired_at', $asset->acquired_at?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('acquired_at')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="acquisition_cost" class="block text-sm font-medium text-gray-700">Nilai Perolehan (Rp)</label>
            <input id="acquisition_cost" name="acquisition_cost" type="number" step="0.01" min="0" value="{{ old('acquisition_cost', $asset->acquisition_cost) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('acquisition_cost')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="warranty_expires_at" class="block text-sm font-medium text-gray-700">Garansi Berakhir</label>
            <input id="warranty_expires_at" name="warranty_expires_at" type="date" value="{{ old('warranty_expires_at', $asset->warranty_expires_at?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('warranty_expires_at')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-3">
            <label for="notes" class="block text-sm font-medium text-gray-700">Catatan</label>
            <textarea id="notes" name="notes" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('notes', $asset->notes) }}</textarea>
            @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
