<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="md:col-span-2">
            @php
                $selectedDepartmentIds = collect(old('departments', $jobPosition->activeDepartments->pluck('id')->all()))->map(fn ($id) => (string) $id);
            @endphp
            <label for="departments" class="block text-sm font-medium text-gray-700">Tersedia untuk Divisi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <select id="departments" name="departments[]" required multiple class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected($selectedDepartmentIds->contains((string) $department->id))>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('departments')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('departments.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="default_role_id" class="block text-sm font-medium text-gray-700">Default Role Login</label>
            <select id="default_role_id" name="default_role_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tidak ada default</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('default_role_id', $jobPosition->default_role_id) == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('default_role_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Kode <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="code" name="code" value="{{ old('code', $jobPosition->code) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Jabatan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
            <input id="name" name="name" value="{{ old('name', $jobPosition->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="level" class="block text-sm font-medium text-gray-700">Level</label>
            <input id="level" name="level" value="{{ old('level', $jobPosition->level) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('level')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-end gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $jobPosition->is_active ?? true)) class="mb-3 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span class="pb-2.5">Aktif</span>
        </label>
    </div>
</section>
