@php
    $contract = old('contract_number') ? null : ($employee->currentContract ?? null);
@endphp

<div class="space-y-4" data-employee-stepper data-placement-form data-placement-catalog='@json($placementCatalog)'>
    <section class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4" role="tablist" aria-label="Tahapan form karyawan">
            <button type="button" role="tab" data-stepper-button="0" class="rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <span class="block text-xs font-medium text-gray-400">01</span>
                Informasi
            </button>
            <button type="button" role="tab" data-stepper-button="1" class="rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <span class="block text-xs font-medium text-gray-400">02</span>
                Penempatan
            </button>
            <button type="button" role="tab" data-stepper-button="2" class="rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <span class="block text-xs font-medium text-gray-400">03</span>
                Kontrak
            </button>
            <button type="button" role="tab" data-stepper-button="3" class="rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <span class="block text-xs font-medium text-gray-400">04</span>
                Akun Login
            </button>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" role="tabpanel" data-stepper-panel="0">
        <h2 class="text-base font-semibold text-gray-950">Informasi Karyawan</h2>

        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="photo" class="block text-sm font-medium text-gray-700">Foto Karyawan</label>
                <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center">
                    @if ($employee->photo_url)
                        <img src="{{ $employee->photo_url }}" alt="Foto {{ $employee->full_name }}" class="size-20 rounded-md border border-gray-200 object-cover">
                    @else
                        <div class="flex size-20 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-lg font-semibold text-gray-500">
                            {{ str($employee->full_name ?: 'K')->substr(0, 1)->upper() }}
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none file:mr-3 file:rounded-sm file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <p class="mt-2 text-xs text-gray-500">Format JPG, PNG, atau WebP. Maksimal 2MB. Resolusi minimal 300x300 px dan maksimal 3000x3000 px.</p>
                        @error('photo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            <div>
                <label for="employee_number" class="block text-sm font-medium text-gray-700">NIK Karyawan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                <input id="employee_number" name="employee_number" value="{{ old('employee_number', $employee->employee_number) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('employee_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Nama Lengkap <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                <input id="full_name" name="full_name" value="{{ old('full_name', $employee->full_name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('full_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" data-employee-email class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Nomor HP</label>
                <input id="phone" name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('phone')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="identity_number" class="block text-sm font-medium text-gray-700">Nomor Identitas</label>
                <input id="identity_number" name="identity_number" value="{{ old('identity_number', $employee->identity_number) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('identity_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="birth_date" class="block text-sm font-medium text-gray-700">Tanggal Lahir</label>
                <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('birth_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="join_date" class="block text-sm font-medium text-gray-700">Tanggal Bergabung <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                <input id="join_date" name="join_date" type="date" value="{{ old('join_date', $employee->join_date?->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('join_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="employment_status" class="block text-sm font-medium text-gray-700">Status Karyawan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                <select id="employment_status" name="employment_status" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($statuses as $status => $label)
                        <option value="{{ $status }}" @selected(old('employment_status', $employee->employment_status ?: 'active') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('employment_status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="address" class="block text-sm font-medium text-gray-700">Alamat</label>
                <textarea id="address" name="address" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('address', $employee->address) }}</textarea>
                @error('address')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" role="tabpanel" data-stepper-panel="1" hidden>
        <h2 class="text-base font-semibold text-gray-950">Penempatan</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi Kerja <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="branch_id" name="branch_id" required data-placement-branch class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $employee->branch_id) == $branch->id)>{{ $branch->name }} · {{ $branch->city }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="department_id" name="department_id" required data-placement-department class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" data-placement-department-option @selected(old('department_id', $employee->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="job_position_id" class="block text-sm font-medium text-gray-700">Jabatan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="job_position_id" name="job_position_id" required data-placement-position class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih jabatan</option>
                        @foreach ($jobPositions as $jobPosition)
                            <option value="{{ $jobPosition->id }}" data-placement-position-option @selected(old('job_position_id', $employee->job_position_id) == $jobPosition->id)>{{ $jobPosition->name }}</option>
                        @endforeach
                    </select>
                    @error('job_position_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" role="tabpanel" data-stepper-panel="2" hidden>
        <h2 class="text-base font-semibold text-gray-950">Kontrak Aktif</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="contract_number" class="block text-sm font-medium text-gray-700">Nomor Kontrak <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <input id="contract_number" name="contract_number" value="{{ old('contract_number', $contract?->contract_number) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @error('contract_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contract_type" class="block text-sm font-medium text-gray-700">Jenis Kontrak <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="contract_type" name="contract_type" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($contractTypes as $type)
                            <option value="{{ $type }}" @selected(old('contract_type', $contract?->contract_type ?: 'PKWT') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('contract_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="contract_start_date" class="block text-sm font-medium text-gray-700">Mulai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <input id="contract_start_date" name="contract_start_date" type="date" value="{{ old('contract_start_date', $contract?->start_date?->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @error('contract_start_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="contract_end_date" class="block text-sm font-medium text-gray-700">Selesai</label>
                        <input id="contract_end_date" name="contract_end_date" type="date" value="{{ old('contract_end_date', $contract?->end_date?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @error('contract_end_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="contract_status" class="block text-sm font-medium text-gray-700">Status Kontrak <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="contract_status" name="contract_status" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($contractStatuses as $status)
                            <option value="{{ $status }}" @selected(old('contract_status', $contract?->status ?: 'active') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                    @error('contract_status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contract_notes" class="block text-sm font-medium text-gray-700">Catatan</label>
                    <textarea id="contract_notes" name="contract_notes" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('contract_notes', $contract?->notes) }}</textarea>
                    @error('contract_notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" role="tabpanel" data-stepper-panel="3" hidden>
        <h2 class="text-base font-semibold text-gray-950">Akun Login</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="login_email_display" class="block text-sm font-medium text-gray-700">Email Login</label>
                    <input id="login_email_display" type="email" value="{{ old('email', $employee->email) }}" data-login-email-display disabled class="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 shadow-xs outline-none">
                </div>
                <div>
                    <label for="login_password" class="block text-sm font-medium text-gray-700">Password Login</label>
                    <input id="login_password" name="login_password" type="password" placeholder="{{ $employee->user ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @error('login_password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="login_role_id" class="block text-sm font-medium text-gray-700">Role Login</label>
                    <select id="login_role_id" name="login_role_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pakai default jabatan</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('login_role_id', $employee->user?->roles->first()?->id) == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('login_role_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                @if ($employee->user)
                    <div class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 md:col-span-3">
                        Akun login aktif: {{ $employee->user->email }}
                    </div>
                @endif
            </div>
    </section>

    <section class="flex flex-col-reverse gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <button type="button" data-stepper-prev hidden class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            Sebelumnya
        </button>
        <div class="flex justify-end gap-2">
            <button type="button" data-stepper-next class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                Selanjutnya
            </button>
            <button type="submit" data-stepper-submit hidden class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                Simpan
            </button>
        </div>
    </section>
</div>
