<x-layouts.app title="{{ $employee->full_name }} - {{ config('app.name', 'HRIS') }}" heading="Detail Karyawan">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <div class="mb-4">
                    @if ($employee->photo_url)
                        <img src="{{ $employee->photo_url }}" alt="Foto {{ $employee->full_name }}" class="size-20 rounded-md border border-gray-200 object-cover shadow-sm">
                    @else
                        <div class="flex size-20 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-2xl font-semibold text-gray-500 shadow-sm">
                            {{ str($employee->full_name ?: 'K')->substr(0, 1)->upper() }}
                        </div>
                    @endif
                </div>
                <p class="text-sm font-medium text-gray-500">{{ $employee->employee_number }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $employee->full_name }}</h1>
                <p class="mt-2 text-sm text-gray-500">{{ $employee->jobPosition?->name ?? '-' }} · {{ $employee->branch?->name ?? '-' }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('employees.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Kembali</a>
                @can('employees.update')
                    <a href="{{ route('employees.edit', $employee) }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Edit</a>
                @endcan
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-950">Status</h2>
            <dl class="mt-5 grid grid-cols-1 gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-gray-500">Status Kepegawaian</dt>
                    <dd class="mt-2">
                        <x-status-badge :tone="$employee->kepegawaian_status_tone">{{ $employee->kepegawaian_status_label }}</x-status-badge>
                    </dd>
                    <p class="mt-2 text-xs text-gray-500">Apakah orang ini masih menjadi karyawan.</p>
                </div>
                <div>
                    <dt class="text-gray-500">Status Kontrak</dt>
                    <dd class="mt-2">
                        @if ($employee->currentContract)
                            <x-status-badge :tone="$employee->currentContract->effective_status_tone">{{ $employee->currentContract->effective_status_label }}</x-status-badge>
                        @else
                            <x-status-badge tone="neutral">Belum ada kontrak aktif</x-status-badge>
                        @endif
                    </dd>
                    <p class="mt-2 text-xs text-gray-500">Kondisi kontrak yang sedang berjalan.</p>
                </div>
                @if ($employee->isInactive())
                    <div>
                        <dt class="text-gray-500">Status Akhir</dt>
                        <dd class="mt-2 font-medium text-gray-950">
                            {{ $employee->exit_reason_label ?? 'Alasan belum diisi' }}
                            @if ($employee->exit_date)
                                <span class="font-normal text-gray-500">· {{ $employee->exit_date->format('d M Y') }}</span>
                            @endif
                        </dd>
                        <p class="mt-2 text-xs text-gray-500">Alasan &amp; tanggal karyawan keluar.</p>
                    </div>
                @endif
            </dl>

            @if ($employee->contract_needs_attention)
                <div class="mt-5 flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        <p>Kontrak berakhir pada <span class="font-semibold">{{ $employee->currentContract->end_date->format('d M Y') }}</span> ({{ abs($employee->currentContract->remaining_days) }} hari lalu), tetapi karyawan masih berstatus <span class="font-semibold">Aktif</span>. Perpanjang kontrak atau proses karyawan keluar.</p>
                    </div>
                    @can('employees.update')
                        <a href="{{ route('employees.edit', $employee) }}" class="shrink-0 self-start rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 sm:self-auto">Perpanjang / Edit Kontrak</a>
                    @endcan
                </div>
            @elseif (! $employee->isInactive() && ! $employee->currentContract)
                <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Karyawan masih berstatus Aktif tetapi belum memiliki kontrak aktif. Tambahkan atau aktifkan kontrak melalui tombol Edit.
                </div>
            @endif
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Tanggal Bergabung</p>
                <p class="mt-2 text-xl font-semibold text-gray-950">{{ $employee->join_date?->format('d M Y') ?? '-' }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Tanggal Keluar</p>
                <p class="mt-2 text-xl font-semibold text-gray-950">{{ $employee->exit_date?->format('d M Y') ?? '-' }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Lama Kontrak Aktif</p>
                <p class="mt-2 text-xl font-semibold text-gray-950">{{ $employee->contract_tenure ?? '-' }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Sisa Kontrak</p>
                <p @class([
                    'mt-2 text-xl font-semibold',
                    'text-red-600' => $employee->currentContract && ! is_null($employee->remaining_contract_days) && $employee->remaining_contract_days <= 30,
                    'text-gray-950' => ! $employee->currentContract || is_null($employee->remaining_contract_days) || $employee->remaining_contract_days > 30,
                ])>
                    @if (! $employee->currentContract)
                        -
                    @elseif (is_null($employee->remaining_contract_days))
                        Tidak terbatas
                    @elseif ($employee->remaining_contract_days > 0)
                        {{ $employee->remaining_contract_days }} hari
                    @else
                        Kedaluwarsa
                    @endif
                </p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_380px]">
            <div class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-950">Informasi Personal</h2>
                    <dl class="mt-5 grid grid-cols-1 gap-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500">Email</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Nomor HP</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Nomor Identitas</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->identity_number ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Tanggal Lahir</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->birth_date?->format('d M Y') ?? '-' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Alamat</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->address ?? '-' }}</dd>
                        </div>
                        @if ($employee->isInactive())
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500">Catatan Status Akhir</dt>
                                <dd class="mt-1 font-medium text-gray-950">{{ $employee->exit_notes ?? '-' }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-950">Histori Kontrak</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nomor</th>
                                    <th>Jenis</th>
                                    <th>Periode</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($employee->contracts as $contract)
                                    <tr>
                                        <td class="font-medium text-gray-950">{{ $contract->contract_number }}</td>
                                        <td>{{ $contract->contract_type }}</td>
                                        <td>
                                            {{ $contract->start_date?->format('d M Y') }} - {{ $contract->end_date?->format('d M Y') ?? 'Tidak terbatas' }}
                                        </td>
                                        <td>
                                            <x-status-badge :tone="$contract->effective_status_tone">{{ $contract->effective_status_label }}</x-status-badge>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="cell-empty">Belum ada kontrak.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-950">Riwayat Kerja</h2>
                    @if ($employee->events->isNotEmpty())
                        <ol class="mt-5">
                            @foreach ($employee->events as $event)
                                <li class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <span @class([
                                            'mt-1.5 size-3 shrink-0 rounded-full',
                                            'bg-emerald-500' => $event->tone === 'success',
                                            'bg-blue-500' => $event->tone === 'info',
                                            'bg-amber-500' => $event->tone === 'warning',
                                            'bg-red-500' => $event->tone === 'danger',
                                            'bg-gray-400' => $event->tone === 'neutral',
                                        ])></span>
                                        @unless ($loop->last)
                                            <span class="my-1 w-px flex-1 bg-gray-200"></span>
                                        @endunless
                                    </div>
                                    <div class="min-w-0 flex-1 pb-6">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                            <p class="text-sm font-semibold text-gray-950">{{ $event->title }}</p>
                                            <time class="text-xs text-gray-500">{{ $event->occurred_at->format('d M Y') }}</time>
                                        </div>
                                        @if ($event->description)
                                            <p class="mt-1 text-sm text-gray-600">{{ $event->description }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-gray-400">oleh {{ $event->causer?->name ?? 'Sistem' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Belum ada riwayat.</p>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-950">Penempatan</h2>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Lokasi</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->branch?->name ?? '-' }}</dd>
                            <dd class="mt-1 text-gray-500">
                                {{ $employee->branch?->type === 'warehouse' ? 'Gudang' : str($employee->branch?->type ?: 'office')->headline() }}
                                @if ($employee->branch?->city)
                                    · {{ $employee->branch->city }}
                                @endif
                            </dd>
                            <dd class="mt-1 text-gray-500">{{ $employee->branch?->address ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Divisi</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->departments->isNotEmpty() ? $employee->departments->pluck('name')->join(', ') : ($employee->department?->name ?? '-') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Jabatan</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->jobPosition?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Atasan Langsung</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->manager?->full_name ?? '-' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-gray-950">Absensi &amp; Perangkat</h2>
                        @can('employees.update')<a href="{{ route('employees.edit', $employee) }}" class="text-xs font-medium text-primary hover:underline">Atur PIN</a>@endcan
                    </div>
                    <p class="mt-1 text-xs text-gray-500">PIN karyawan ini di mesin sidik jari. Dipakai untuk mencocokkan absensi otomatis.</p>
                    @if ($employee->deviceMappings->isNotEmpty())
                        <ul class="mt-4 space-y-2">
                            @foreach ($employee->deviceMappings as $mapping)
                                <li class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm">
                                    <span class="min-w-0">
                                        <span class="block truncate text-gray-800">{{ $mapping->device?->name ?? 'Semua mesin' }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $mapping->device ? ($mapping->device->branch?->name ?? 'Tanpa lokasi') : 'Berlaku untuk semua mesin' }}</span>
                                    </span>
                                    <span class="shrink-0 rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-800">PIN {{ $mapping->machine_user_id }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mt-4 rounded-md border border-dashed border-gray-200 px-3 py-3 text-sm text-gray-500">
                            Belum ada PIN mesin. @can('employees.update')<a href="{{ route('employees.edit', $employee) }}" class="text-primary hover:underline">Tambahkan di form karyawan</a> agar absensinya bisa dicocokkan otomatis.@endcan
                        </div>
                    @endif
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-950">Kontrak Aktif</h2>
                    @if ($employee->currentContract)
                        <dl class="mt-5 space-y-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Nomor Kontrak</dt>
                                <dd class="mt-1 font-medium text-gray-950">{{ $employee->currentContract->contract_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Jenis</dt>
                                <dd class="mt-1 font-medium text-gray-950">{{ $employee->currentContract->contract_type }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Catatan</dt>
                                <dd class="mt-1 text-gray-700">{{ $employee->currentContract->notes ?? '-' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Belum ada kontrak aktif.</p>
                    @endif
                </section>

                @can('employees.update')
                    @php
                        $renewHasErrors = $errors->hasAny(['contract_number', 'contract_type', 'start_date', 'end_date']);
                        $isReactivation = $employee->isInactive();
                    @endphp
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" data-renew-form>
                        <details @if ($renewHasErrors || $isReactivation) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between text-base font-semibold text-gray-950">
                                {{ $isReactivation ? 'Aktifkan Kembali Karyawan' : 'Perpanjang Kontrak' }}
                                <span class="rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-600">{{ $isReactivation ? '+ Rekrut ulang' : '+ Kontrak baru' }}</span>
                            </summary>
                            <p class="mt-3 text-xs text-gray-500">
                                {{ $isReactivation
                                    ? 'Karyawan akan diaktifkan kembali (status Aktif) dan kontrak baru dibuat sebagai kontrak aktif.'
                                    : 'Kontrak aktif saat ini akan ditandai "Diperpanjang" dan kontrak baru dibuat sebagai kontrak aktif.' }}
                            </p>
                            <form method="POST" action="{{ route('employees.renew-contract', $employee) }}" class="mt-4 space-y-4">
                                @csrf
                                <div>
                                    <label for="renew_contract_number" class="block text-sm font-medium text-gray-700">Nomor Kontrak Baru <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                    <input id="renew_contract_number" name="contract_number" value="{{ old('contract_number') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    @error('contract_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="renew_contract_type" class="block text-sm font-medium text-gray-700">Jenis Kontrak <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                    <select id="renew_contract_type" name="contract_type" required data-contract-type-toggle="#renew_end_date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        @foreach ($contractTypes as $type)
                                            <option value="{{ $type }}" @selected(old('contract_type', 'PKWT') === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    @error('contract_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="renew_start_date" class="block text-sm font-medium text-gray-700">Mulai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                        <input id="renew_start_date" name="start_date" type="date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        @error('start_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="renew_end_date" class="block text-sm font-medium text-gray-700">Selesai</label>
                                        <input id="renew_end_date" name="end_date" type="date" value="{{ old('end_date') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        @error('end_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="renew_notes" class="block text-sm font-medium text-gray-700">Catatan</label>
                                    <textarea id="renew_notes" name="notes" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('notes') }}</textarea>
                                    @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                                    {{ $isReactivation ? 'Aktifkan Kembali & Simpan Kontrak' : 'Simpan Kontrak Baru' }}
                                </button>
                            </form>
                        </details>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-base font-semibold text-gray-950">Status Akhir Karyawan</h2>

                        @if ($employee->isInactive())
                            <dl class="mt-5 space-y-4 text-sm">
                                <div>
                                    <dt class="text-gray-500">Status Kepegawaian</dt>
                                    <dd class="mt-1"><x-status-badge :tone="$employee->kepegawaian_status_tone">{{ $employee->kepegawaian_status_label }}</x-status-badge></dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Alasan Keluar</dt>
                                    <dd class="mt-1 font-medium text-gray-950">{{ $employee->exit_reason_label ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Tanggal Keluar</dt>
                                    <dd class="mt-1 font-medium text-gray-950">{{ $employee->exit_date?->format('d M Y') ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Akun Login</dt>
                                    <dd class="mt-1 font-medium text-gray-950">{{ $employee->user?->is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                                </div>
                            </dl>
                        @else
                            <form method="POST" action="{{ route('employees.resign', $employee) }}" class="mt-5 space-y-4">
                                @csrf
                                @method('PATCH')

                                <div>
                                    <label for="exit_reason" class="block text-sm font-medium text-gray-700">Alasan Keluar <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                    <select id="exit_reason" name="exit_reason" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        @foreach (\App\Models\Employee::exitReasonLabels() as $reason => $label)
                                            <option value="{{ $reason }}" @selected(old('exit_reason', 'resigned') === $reason)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('exit_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="exit_date" class="block text-sm font-medium text-gray-700">Tanggal Keluar <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                    <input id="exit_date" name="exit_date" type="date" value="{{ old('exit_date', now()->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    @error('exit_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="exit_notes" class="block text-sm font-medium text-gray-700">Catatan Keluar</label>
                                    <textarea id="exit_notes" name="exit_notes" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('exit_notes') }}</textarea>
                                    @error('exit_notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <button type="submit" class="w-full rounded-md border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                                    Proses Karyawan Keluar
                                </button>
                            </form>
                        @endif
                    </section>
                @endcan
            </aside>
        </section>
    </div>
</x-layouts.app>
