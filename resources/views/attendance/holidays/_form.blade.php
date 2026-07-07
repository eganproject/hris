<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="date" class="block text-sm font-medium text-gray-700">Tanggal <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="date" name="date" type="date" value="{{ old('date', $holiday->date?->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Hari Libur <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $holiday->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. Hari Kemerdekaan RI">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" name="is_national" value="1" @checked(old('is_national', $holiday->is_national ?? true)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                Berlaku nasional (semua lokasi)
            </label>
            <p class="mt-2 text-xs text-gray-500">Nonaktifkan jika libur hanya berlaku untuk satu lokasi.</p>
        </div>
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi (jika bukan nasional)</label>
            <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">— Pilih lokasi —</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $holiday->branch_id) == $branch->id)>{{ $branch->name }} · {{ $branch->city }}</option>
                @endforeach
            </select>
            @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="notes" class="block text-sm font-medium text-gray-700">Catatan</label>
            <textarea id="notes" name="notes" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('notes', $holiday->notes) }}</textarea>
            @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
