<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="serial_number" class="block text-sm font-medium text-gray-700">Serial Number (SN) <span class="field-requirement is-required">*</span></label>
            <input id="serial_number" name="serial_number" value="{{ old('serial_number', $device->serial_number) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. ABCD1234567">
            <p class="mt-2 text-xs text-gray-500">Harus sama persis dengan SN di mesin (Menu → Info Sistem). Ini kunci allowlist device.</p>
            @error('serial_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Perangkat <span class="field-requirement is-required">*</span></label>
            <input id="name" name="name" value="{{ old('name', $device->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. Mesin Absen Lobby">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi</label>
            <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">— Tidak terikat lokasi —</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $device->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="timezone" class="block text-sm font-medium text-gray-700">Zona Waktu</label>
            <input id="timezone" name="timezone" value="{{ old('timezone', $device->timezone ?? 'Asia/Jakarta') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            <p class="mt-2 text-xs text-gray-500">Zona waktu jam pada mesin. Default Asia/Jakarta.</p>
        </div>
        <label class="flex items-end gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true)) class="mb-3 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span class="pb-2.5">Aktif (terima data)</span>
        </label>
    </div>
</section>
