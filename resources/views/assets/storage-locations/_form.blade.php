@php $isEdit = $storageLocation->exists; @endphp

<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi Kerja @unless ($isEdit)<span class="field-requirement is-required" aria-label="Wajib diisi">*</span>@endunless</label>
            @if ($isEdit)
                {{-- Terkunci setelah dibuat: memindahkannya ke cabang lain berarti ikut
                     memindahkan seluruh rak di bawahnya beserta aset yang menunjuk ke
                     sana — itu perpindahan barang, bukan penyuntingan master. --}}
                <div class="mt-2 flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <span class="text-sm text-gray-900">{{ $storageLocation->branch?->name }}</span>
                    <span class="text-xs text-gray-500">Tidak bisa dipindah ke lokasi kerja lain.</span>
                </div>
            @else
                <select id="branch_id" name="branch_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Pilih lokasi kerja</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $storageLocation->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
            @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="parent_id" class="block text-sm font-medium text-gray-700">Berada di Dalam</label>
            <select id="parent_id" name="parent_id" data-parent-select class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">— Jenjang teratas —</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" data-branch="{{ $parent->branch_id }}" @selected((string) old('parent_id', $storageLocation->parent_id) === (string) $parent->id)>{{ $parent->full_path }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Kosongkan untuk jenjang teratas (mis. "Lantai 4"). Maksimal {{ \App\Models\AssetStorageLocation::MAX_DEPTH }} jenjang.</p>
            @error('parent_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Tempat <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $storageLocation->name) }}" required maxlength="120" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Gudang A">
            <p class="mt-1 text-xs text-gray-500">Tulis namanya saja, tanpa induknya — jalur lengkapnya disusun sendiri.</p>
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Kode</label>
            <input id="code" name="code" value="{{ old('code', $storageLocation->code) }}" maxlength="30" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="GDG-A">
            <p class="mt-1 text-xs text-gray-500">Opsional, untuk label fisik di rak.</p>
            @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-start gap-2 text-sm font-medium text-gray-700 md:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $storageLocation->is_active ?? true)) class="mt-0.5 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span>Aktif
                <span class="mt-0.5 block text-xs font-normal text-gray-500">Tempat nonaktif tidak bisa dipilih untuk aset baru, tapi aset yang sudah ada di sana tetap tercatat.</span>
            </span>
        </label>
    </div>
</section>

@unless ($isEdit)
    @push('scripts')
    <script>
        // Induk harus berada di lokasi kerja yang sama, jadi pilihannya mengikuti
        // lokasi kerja yang sedang dipilih.
        (function () {
            const branch = document.getElementById('branch_id');
            const parent = document.querySelector('[data-parent-select]');

            if (!branch || !parent) return;

            const sync = () => {
                let current = parent.value;

                Array.from(parent.options).forEach((option) => {
                    if (!option.value) return;

                    const matches = option.dataset.branch === branch.value;
                    option.hidden = !matches;
                    option.disabled = !matches;

                    if (!matches && option.value === current) current = '';
                });

                parent.value = current;
            };

            branch.addEventListener('change', sync);
            sync();
        })();
    </script>
    @endpush
@endunless
