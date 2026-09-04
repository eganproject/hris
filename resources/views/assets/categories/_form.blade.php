<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Kode Kategori <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="code" name="code" value="{{ old('code', $category->code) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="LAPTOP">
            @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Kategori <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Laptop">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="asset_prefix" class="block text-sm font-medium text-gray-700">Prefix Kode Aset <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="asset_prefix" name="asset_prefix" value="{{ old('asset_prefix', $category->asset_prefix) }}" required maxlength="10" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm uppercase shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="LPT">
            <p class="mt-1 text-xs text-gray-500">Muncul di kode aset: <span class="font-mono">AST-<span class="font-semibold">LPT</span>-HO-0012</span>. Huruf dan angka saja.</p>
            @error('asset_prefix')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="useful_life_months" class="block text-sm font-medium text-gray-700">Umur Ekonomis (bulan)</label>
            <input id="useful_life_months" name="useful_life_months" type="number" min="1" max="1200" value="{{ old('useful_life_months', $category->useful_life_months) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="48">
            <p class="mt-1 text-xs text-gray-500">Opsional. Dipakai nanti untuk perhitungan penyusutan.</p>
            @error('useful_life_months')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-start gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="requires_serial" value="1" @checked(old('requires_serial', $category->requires_serial ?? false)) class="mt-0.5 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span>Wajib nomor seri
                <span class="mt-0.5 block text-xs font-normal text-gray-500">Registrasi aset kategori ini tidak bisa disimpan tanpa nomor seri.</span>
            </span>
        </label>
        <label class="flex items-start gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="mt-0.5 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span>Aktif
                <span class="mt-0.5 block text-xs font-normal text-gray-500">Kategori nonaktif tidak bisa dipilih untuk aset baru, tapi aset lama tetap terbaca.</span>
            </span>
        </label>
        <div class="md:col-span-2">
            <label for="description" class="block text-sm font-medium text-gray-700">Keterangan</label>
            <textarea id="description" name="description" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('description', $category->description) }}</textarea>
            @error('description')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
