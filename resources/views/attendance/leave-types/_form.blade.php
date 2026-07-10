<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Kode <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="code" name="code" value="{{ old('code', $leaveType->code) }}" required maxlength="30" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. CT, SAKIT, IZIN">
            @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Jenis Cuti <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $leaveType->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. Cuti Tahunan">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="attendance_status" class="block text-sm font-medium text-gray-700">Status Absensi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="attendance_status" name="attendance_status" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('attendance_status', $leaveType->attendance_status?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-gray-500">Menentukan tampil sebagai apa di absensi saat cuti ini disetujui.</p>
            @error('attendance_status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="default_quota_days" class="block text-sm font-medium text-gray-700">Kuota Default (hari/tahun)</label>
            <input id="default_quota_days" name="default_quota_days" type="number" min="0" max="365" value="{{ old('default_quota_days', $leaveType->default_quota_days) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. 12">
            <p class="mt-2 text-xs text-gray-500">Hanya berlaku jika "Memakai kuota" dicentang. Bisa ditimpa per karyawan di menu Kuota Cuti.</p>
            @error('default_quota_days')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="space-y-3 md:col-span-2">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" name="counts_against_balance" value="1" @checked(old('counts_against_balance', $leaveType->counts_against_balance)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                Memakai kuota (mengurangi jatah cuti tahunan)
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid', $leaveType->is_paid ?? true)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                Berbayar (tetap digaji)
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $leaveType->is_active ?? true)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                Aktif (bisa dipilih saat pengajuan)
            </label>
        </div>
    </div>
</section>
