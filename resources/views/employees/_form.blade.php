@php
    $contract = old('contract_number') ? null : ($employee->currentContract ?? null);
    // Selecting "Nonaktif" runs the exit flow; that is possible whenever the employee
    // is not already inactive (a new employee, or an active one being edited).
    $canExit = ! ($employee->exists && $employee->isInactive());
    $exitModalOpen = $errors->hasAny(['exit_reason', 'exit_date', 'exit_notes']);
@endphp

<div class="space-y-4" data-employee-stepper data-placement-form data-placement-catalog='@json($placementCatalog)'
    data-exit-form
    data-exit-active="{{ $canExit ? 'true' : 'false' }}"
    data-exit-open="{{ $exitModalOpen ? 'true' : 'false' }}"
    data-reactivate-active="{{ $employee->exists && $employee->isInactive() ? 'true' : 'false' }}">
    <section class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
        <div class="grid grid-cols-2 gap-2 lg:grid-cols-5" role="tablist" aria-label="Tahapan form karyawan">
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
                Saldo Cuti
            </button>
            <button type="button" role="tab" data-stepper-button="4" class="rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <span class="block text-xs font-medium text-gray-400">05</span>
                Akun Login
            </button>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" role="tabpanel" data-stepper-panel="0">
        <h2 class="text-base font-semibold text-gray-950">Informasi Karyawan</h2>

        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="md:col-span-2" data-image-field>
                <label for="photo" class="block text-sm font-medium text-gray-700">Foto Karyawan</label>
                <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <img
                        @if ($employee->photo_url) src="{{ $employee->photo_url }}" @else hidden @endif
                        alt="Pratinjau foto {{ $employee->full_name }}"
                        data-image-preview
                        class="size-20 rounded-md border border-gray-200 object-cover">
                    <div
                        data-image-placeholder
                        @if ($employee->photo_url) hidden @endif
                        class="flex size-20 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-lg font-semibold text-gray-500">
                        {{ str($employee->full_name ?: 'K')->substr(0, 1)->upper() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-image-input class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none file:mr-3 file:rounded-sm file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <p class="mt-2 text-xs text-gray-500">Format JPG, PNG, atau WebP. Maksimal 2MB. Resolusi minimal 300x300 px dan maksimal 3000x3000 px.</p>
                        @error('photo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Kode Karyawan</label>
                <p class="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-700">
                    {{ $employee->employee_number ?? 'Dibuat otomatis setelah disimpan' }}
                </p>
                <p class="mt-2 text-xs text-gray-500">Format otomatis <span class="font-medium">COK[bulan][tahun bergabung]-[kode lokasi][id]</span>, mis. <span class="font-medium">COK0726-HO0012</span>. Kode ikut menyesuaikan bila tanggal bergabung atau lokasi kerja diubah.</p>
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
                <label for="employment_status" class="block text-sm font-medium text-gray-700">Status Kepegawaian <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                <select id="employment_status" name="employment_status" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($statuses as $status => $label)
                        <option value="{{ $status }}" @selected(old('employment_status', $employee->employment_status ?: 'active') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-gray-500">Pilih <span class="font-medium">Nonaktif</span> untuk memproses karyawan keluar — Anda akan diminta mengisi alasan &amp; tanggal keluar saat menyimpan. Pilih <span class="font-medium">Aktif</span> pada karyawan yang sudah keluar untuk mengaktifkannya kembali.</p>
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
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi Jabatan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="department_id" name="department_id" required data-placement-department class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" data-placement-department-option @selected(old('department_id', $employee->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-500">Divisi tempat jabatan di atas berada.</p>
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
                <div>
                    <label for="manager_id" class="block text-sm font-medium text-gray-700">Atasan Langsung</label>
                    <select id="manager_id" name="manager_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">— Tidak ada —</option>
                        @foreach ($managers as $manager)
                            @continue($employee->exists && $manager->id === $employee->id)
                            <option value="{{ $manager->id }}" @selected(old('manager_id', $employee->manager_id) == $manager->id)>{{ $manager->full_name }} · {{ $manager->employee_number }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-500">Penyetuju pertama untuk pengajuan cuti/izin karyawan ini.</p>
                    @error('manager_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @php
                    $selectedExtra = collect(old('department_ids', $employee->exists ? $employee->departments->pluck('id')->all() : []))
                        ->map(fn ($id) => (int) $id)->all();
                @endphp
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700">Divisi Lain <span class="text-gray-400">(opsional)</span></label>
                    <p class="mt-1 text-xs text-gray-500">Centang bila karyawan ini juga bekerja di divisi lain. Semua divisi dianggap setara — hanya yang tersedia di lokasi kerja terpilih yang bisa dicentang.</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" data-extra-departments>
                        @foreach ($departments as $department)
                            <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700" data-extra-department-option data-department-id="{{ $department->id }}">
                                <input type="checkbox" name="department_ids[]" value="{{ $department->id }}" @checked(in_array($department->id, $selectedExtra, true)) class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                                <span>{{ $department->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('department_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-3" data-pin-repeater>
                    @php
                        $pinRows = old('machine_pins');
                        if ($pinRows === null) {
                            $pinRows = $employee->exists
                                ? $employee->deviceMappings->map(fn ($m) => ['device_id' => $m->device_id, 'machine_user_id' => $m->machine_user_id])->values()->all()
                                : [];
                        }
                    @endphp
                    <label class="block text-sm font-medium text-gray-700">PIN Mesin Absensi <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <p class="mt-1 text-xs text-gray-500">Minimal satu PIN wajib diisi. Pilih <span class="font-medium">Semua mesin</span> bila PIN sama di semua mesin, atau tambahkan baris per mesin bila berbeda.</p>
                    @error('machine_pins')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                    <div class="mt-3 space-y-2" data-pin-rows>
                        @if (count($pinRows))
                            @foreach ($pinRows as $i => $row)
                                @include('employees._pin-row', ['index' => $i, 'row' => $row])
                            @endforeach
                        @else
                            @include('employees._pin-row', ['index' => 0, 'row' => ['device_id' => null, 'machine_user_id' => '']])
                        @endif
                    </div>

                    <button type="button" data-pin-add class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">+ Tambah PIN mesin</button>

                    <template data-pin-template>
                        @include('employees._pin-row', ['index' => '__IDX__', 'row' => ['device_id' => null, 'machine_user_id' => '']])
                    </template>
                </div>
            </div>
    </section>

    @push('scripts')
    <script>
        (function () {
            const rep = document.querySelector('[data-pin-repeater]');
            if (!rep) return;
            const rows = rep.querySelector('[data-pin-rows]');
            const tpl = rep.querySelector('[data-pin-template]');

            let idx = 0;
            rows.querySelectorAll('[data-pin-row]').forEach(function (r) {
                const el = r.querySelector('select, input');
                const m = el && el.name.match(/machine_pins\[(\d+)\]/);
                if (m) idx = Math.max(idx, parseInt(m[1]) + 1);
            });

            rep.querySelector('[data-pin-add]').addEventListener('click', function () {
                const wrap = document.createElement('div');
                wrap.innerHTML = tpl.innerHTML.replace(/__IDX__/g, idx++).trim();
                rows.appendChild(wrap.firstElementChild);
            });

            rows.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-pin-remove]');
                if (!btn) return;
                btn.closest('[data-pin-row]').remove();
            });
        })();
    </script>
    @endpush

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
                    <select id="contract_type" name="contract_type" required data-contract-type-toggle="#contract_end_date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($contractTypes as $type)
                            <option value="{{ $type }}" @selected(old('contract_type', $contract?->contract_type ?: 'PKWT') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-500">PKWTT (kontrak tetap) tidak memerlukan tanggal selesai.</p>
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
                        @foreach ($contractStatuses as $status => $label)
                            <option value="{{ $status }}" @selected(old('contract_status', $contract?->status ?: 'active') === $status)>{{ $label }}</option>
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
        <h2 class="text-base font-semibold text-gray-950">Saldo Cuti</h2>
        <p class="mt-1 text-sm text-gray-500">Kuota cuti untuk tahun {{ now()->year }}. Kosongkan sebuah kolom untuk memakai kuota default jenis cuti tersebut.</p>
        @php
            $leaveYear = now()->year;
            $balanceOverrides = $employee->exists
                ? $employee->leaveBalances->where('year', $leaveYear)->keyBy('leave_type_id')
                : collect();
        @endphp
        @if ($leaveTypes->isEmpty())
            <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500">
                Belum ada jenis cuti yang memakai kuota. Tambahkan lebih dulu di menu <span class="font-medium">Jenis Cuti</span>.
            </div>
        @else
            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($leaveTypes as $type)
                    @php
                        $default = (int) ($type->default_quota_days ?? 0);
                        $current = old("leave_balance.{$type->id}", optional($balanceOverrides->get($type->id))->quota_days ?? $default);
                    @endphp
                    <div>
                        <label for="leave_balance_{{ $type->id }}" class="block text-sm font-medium text-gray-700">{{ $type->name }}</label>
                        <div class="mt-2 flex items-center gap-2">
                            <input id="leave_balance_{{ $type->id }}" name="leave_balance[{{ $type->id }}]" type="number" min="0" max="365" inputmode="numeric" value="{{ $current }}" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <span class="shrink-0 text-sm text-gray-500">hari</span>
                        </div>
                        <p class="mt-1.5 text-xs text-gray-400">Default: {{ $default }} hari / tahun.</p>
                        @error("leave_balance.{$type->id}")<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-gray-400">Kuota per tahun lainnya dapat dikelola massal di menu <span class="font-medium">Kuota Cuti</span>.</p>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" role="tabpanel" data-stepper-panel="4" hidden>
        <h2 class="text-base font-semibold text-gray-950">Akun Login</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="login_email_display" class="block text-sm font-medium text-gray-700">Email Login</label>
                    <input id="login_email_display" type="email" value="{{ old('email', $employee->email) }}" data-login-email-display disabled class="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 shadow-xs outline-none">
                </div>
                <div>
                    <label for="login_password" class="block text-sm font-medium text-gray-700">Password Login</label>
                    <div class="relative mt-2">
                        <input id="login_password" name="login_password" type="password" placeholder="{{ $employee->user ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' }}" class="block w-full rounded-md border border-gray-300 py-2.5 pl-3 pr-10 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <button type="button" data-password-toggle="login_password" aria-label="Tampilkan password" aria-pressed="false" class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-gray-400 transition hover:text-gray-600 focus:text-gray-600 focus:outline-none">
                            <x-icon name="eye" data-password-show/>
                            <x-icon name="eye-off" data-password-hide hidden/>
                        </button>
                    </div>
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

    @if ($canExit)
        {{-- Verifikasi keluar: muncul saat Status Kepegawaian diubah menjadi "Nonaktif" lalu disimpan. --}}
        <div data-exit-modal @unless ($exitModalOpen) hidden @endunless class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
            <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="exit-modal-title">
                <h2 id="exit-modal-title" class="text-base font-semibold text-gray-950">Proses Karyawan Keluar</h2>
                <p class="mt-1 text-sm text-gray-500">Status kepegawaian diubah menjadi <span class="font-medium">Nonaktif</span>, sehingga karyawan akan dinonaktifkan. Lengkapi data berikut untuk memproses status keluar.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="exit_reason" class="block text-sm font-medium text-gray-700">Alasan Keluar <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <select id="exit_reason" name="exit_reason" data-exit-field class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @foreach ($exitReasons as $reason => $label)
                                <option value="{{ $reason }}" @selected(old('exit_reason', 'contract_ended') === $reason)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('exit_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="exit_date" class="block text-sm font-medium text-gray-700">Tanggal Keluar <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <input id="exit_date" name="exit_date" type="date" value="{{ old('exit_date', now()->format('Y-m-d')) }}" data-exit-field class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @error('exit_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="exit_notes" class="block text-sm font-medium text-gray-700">Catatan Keluar</label>
                        <textarea id="exit_notes" name="exit_notes" rows="3" data-exit-field class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('exit_notes') }}</textarea>
                        @error('exit_notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-exit-cancel class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                    <button type="button" data-exit-confirm class="rounded-md border border-red-200 bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Ya, proses keluar &amp; simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
