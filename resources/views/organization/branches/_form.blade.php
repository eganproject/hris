<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Kode <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="code" name="code" value="{{ old('code', $branch->code) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Lokasi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $branch->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Jenis <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="type" name="type" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="office" @selected(old('type', $branch->type) === 'office')>Office</option>
                <option value="warehouse" @selected(old('type', $branch->type) === 'warehouse')>Gudang</option>
            </select>
            @error('type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="city" class="block text-sm font-medium text-gray-700">Kota</label>
            <input id="city" name="city" value="{{ old('city', $branch->city) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('city')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="province" class="block text-sm font-medium text-gray-700">Provinsi</label>
            <input id="province" name="province" value="{{ old('province', $branch->province) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('province')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-end gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active ?? true)) class="mb-3 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span class="pb-2.5">Aktif</span>
        </label>
        <div class="md:col-span-2">
            <label for="address" class="block text-sm font-medium text-gray-700">Alamat</label>
            <textarea id="address" name="address" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('address', $branch->address) }}</textarea>
            @error('address')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
