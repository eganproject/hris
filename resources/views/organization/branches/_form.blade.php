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

<section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-950">Absen lewat aplikasi HP</h2>
    <p class="mt-1 text-sm text-gray-500">Opsional. Lokasi yang belum diaktifkan tetap memakai mesin absen. Karyawan di lokasi ini absen dengan selfie + titik GPS; absen di luar radius tetap tercatat tetapi ditandai untuk ditinjau HR, kecuali mode ketat dinyalakan.</p>
    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 md:col-span-2">
            <input type="checkbox" name="mobile_attendance_enabled" value="1" @checked(old('mobile_attendance_enabled', $branch->mobile_attendance_enabled)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span>Absen lewat aplikasi</span>
        </label>
        <div>
            <label for="latitude" class="block text-sm font-medium text-gray-700">Latitude titik kantor</label>
            <input id="latitude" name="latitude" inputmode="decimal" value="{{ old('latitude', $branch->latitude) }}" placeholder="-6.2000000" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('latitude')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="longitude" class="block text-sm font-medium text-gray-700">Longitude titik kantor</label>
            <input id="longitude" name="longitude" inputmode="decimal" value="{{ old('longitude', $branch->longitude) }}" placeholder="106.8166667" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('longitude')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-gray-500">Salin dari Google Maps: tekan lama titik kantor, angka pertama latitude, kedua longitude.</p>
        </div>
        <div>
            <label for="geofence_radius_m" class="block text-sm font-medium text-gray-700">Radius (meter)</label>
            <input id="geofence_radius_m" name="geofence_radius_m" type="number" min="20" max="5000" value="{{ old('geofence_radius_m', $branch->geofence_radius_m ?? 100) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('geofence_radius_m')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-end gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="geofence_strict" value="1" @checked(old('geofence_strict', $branch->geofence_strict)) class="mb-3 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span class="pb-2.5">Mode ketat: tolak absen di luar radius</span>
        </label>
        <div class="md:col-span-2">
            <label for="wifi_bssids" class="block text-sm font-medium text-gray-700">BSSID Wi-Fi kantor (opsional)</label>
            <textarea id="wifi_bssids" name="wifi_bssids" rows="2" placeholder="a4:2b:b0:12:34:56" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('wifi_bssids', implode("\n", $branch->wifi_bssids ?? [])) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Satu per baris. HP yang tersambung ke Wi-Fi ini dianggap berada di kantor walau GPS-nya meleset (berguna di gedung bertingkat).</p>
            @error('wifi_bssids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
