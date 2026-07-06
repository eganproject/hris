<x-layouts.app title="Manajemen Karyawan - {{ config('app.name', 'HRIS') }}" heading="Manajemen Karyawan">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Employee module</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Manajemen Karyawan</h1>
            </div>

            @can('employees.create')
                <a href="{{ route('employees.create') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    Tambah Karyawan
                </a>
            @endcan
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Total Karyawan" :value="number_format($summary['total'])" tone="primary">
                <x-icon name="users" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Karyawan Aktif" :value="number_format($summary['active'])" tone="emerald">
                <x-icon name="user-check" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Tidak Bekerja" :value="number_format($summary['inactive'])" tone="rose">
                <x-icon name="user-x" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Lokasi Aktif" :value="number_format($summary['locations'])" tone="sky">
                <x-icon name="map-pin" class="size-5"/>
            </x-stat-card>
            <x-stat-card label="Kontrak Habis 30 Hari" :value="number_format($summary['expiring_contracts'])" tone="amber">
                <x-icon name="calendar-clock" class="size-5"/>
            </x-stat-card>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Nama, NIK karyawan, email">
                </div>
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi</label>
                    <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi</label>
                    <select id="department_id" name="department_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="exit_reason" class="block text-sm font-medium text-gray-700">Alasan Keluar</label>
                    <select id="exit_reason" name="exit_reason" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua alasan</option>
                        @foreach ($exitReasons as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['exit_reason'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="per_page" class="block text-sm font-medium text-gray-700">Per halaman</label>
                    <select id="per_page" name="per_page" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected(($perPage ?? 15) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-full flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:w-auto">
                        Filter
                    </button>
                    <a href="{{ route('employees.index') }}" class="w-full rounded-md border border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Daftar Karyawan</h2>
                <p class="mt-1 text-sm text-gray-500">Informasi lokasi, posisi, status aktif, dan kontrak berjalan.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th>Lokasi</th>
                            <th>Jabatan</th>
                            <th>Kontrak</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if ($employee->photo_url)
                                            <img src="{{ $employee->photo_url }}" alt="Foto {{ $employee->full_name }}" class="size-9 shrink-0 rounded-full border border-gray-200 object-cover">
                                        @else
                                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-100 text-sm font-semibold text-gray-500">
                                                {{ str($employee->full_name ?: 'K')->substr(0, 1)->upper() }}
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('employees.show', $employee) }}" class="font-medium text-gray-950 hover:underline">
                                                {{ $employee->full_name ?? 'Tanpa nama' }}
                                            </a>
                                            <p class="mt-0.5 truncate text-xs text-gray-500">{{ $employee->employee_number ?? 'Belum ada NIK' }} · {{ $employee->email ?? '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-medium text-gray-800">{{ $employee->branch?->name ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $employee->branch?->city ?? '-' }}</p>
                                </td>
                                <td>
                                    <p>{{ $employee->jobPosition?->name ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $employee->department?->name ?? '-' }}</p>
                                </td>
                                <td>
                                    @if ($employee->currentContract)
                                        <p class="font-medium text-gray-800">{{ $employee->currentContract->contract_type }} · {{ $employee->currentContract->contract_number }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ $employee->currentContract->start_date?->format('d M Y') }} - {{ $employee->currentContract->end_date?->format('d M Y') ?? 'Tidak terbatas' }}
                                        </p>
                                        @if (! is_null($employee->remaining_contract_days))
                                            <p @class([
                                                'mt-0.5 text-xs font-medium',
                                                'text-red-600' => $employee->remaining_contract_days <= 30,
                                                'text-gray-500' => $employee->remaining_contract_days > 30,
                                            ])>
                                                {{ $employee->remaining_contract_days >= 0 ? $employee->remaining_contract_days.' hari tersisa' : 'Sudah berakhir '.abs($employee->remaining_contract_days).' hari lalu' }}
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-gray-400">Belum ada kontrak</span>
                                    @endif
                                </td>
                                <td>
                                    <x-status-badge :tone="$employee->kepegawaian_status_tone">{{ $employee->kepegawaian_status_label }}</x-status-badge>
                                    @if ($employee->isInactive())
                                        <p class="mt-1.5 text-xs text-gray-500">
                                            {{ $employee->exit_reason_label ?? 'Alasan belum diisi' }}@if ($employee->exit_date) · {{ $employee->exit_date->format('d M Y') }}@endif
                                        </p>
                                    @elseif ($employee->contract_needs_attention)
                                        <p class="mt-1.5 text-xs font-medium text-red-600">Kontrak kedaluwarsa</p>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-action-menu>
                                        <a href="{{ route('employees.show', $employee) }}" class="action-menu-item"><x-icon name="eye"/> Detail</a>
                                        @can('employees.update')
                                            <a href="{{ route('employees.edit', $employee) }}" class="action-menu-item"><x-icon name="pencil"/> Edit</a>
                                            @if ($employee->isInactive())
                                                <button type="button" class="action-menu-item" data-open-renew data-mode="reactivate" data-url="{{ route('employees.renew-contract', $employee) }}" data-name="{{ $employee->full_name }}"><x-icon name="refresh"/> Aktifkan Kembali</button>
                                            @else
                                                <button type="button" class="action-menu-item" data-open-renew data-mode="renew" data-url="{{ route('employees.renew-contract', $employee) }}" data-name="{{ $employee->full_name }}"><x-icon name="refresh"/> Perpanjang Kontrak</button>
                                                <button type="button" class="action-menu-item" data-open-exit data-url="{{ route('employees.resign', $employee) }}" data-name="{{ $employee->full_name }}"><x-icon name="user-x"/> Proses Keluar</button>
                                            @endif
                                        @endcan
                                        @can('employees.delete')
                                            <form method="POST" action="{{ route('employees.destroy', $employee) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                            </form>
                                        @endcan
                                    </x-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="cell-empty">Belum ada data karyawan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $employees->links() }}
            </div>
        </section>

        @can('employees.update')
            @php
                $renewFlash = session('renew_employee');
                $renewOpen = $renewFlash && $errors->hasAny(['contract_number', 'contract_type', 'start_date', 'end_date']);
                $renewMode = $renewFlash['mode'] ?? 'renew';
                $exitFlash = session('resign_employee');
                $exitOpen = $exitFlash && $errors->hasAny(['exit_reason', 'exit_date', 'exit_notes']);
            @endphp

            {{-- Perpanjang kontrak / aktifkan kembali langsung dari daftar --}}
            <div data-list-modal="renew" @unless ($renewOpen) hidden @endunless class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
                <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <h2 class="text-base font-semibold text-gray-950">
                        <span data-renew-heading>{{ $renewMode === 'reactivate' ? 'Aktifkan Kembali' : 'Perpanjang Kontrak' }}</span><span class="font-normal text-gray-500" data-renew-name>{{ $renewOpen ? ' — '.$renewFlash['name'] : '' }}</span>
                    </h2>
                    <p class="mt-1 text-sm text-gray-500" data-renew-desc>Kontrak baru dibuat sebagai kontrak aktif. Kontrak sebelumnya ditandai "Diperpanjang".</p>
                    <form method="POST" data-list-renew-form data-no-confirm="true" action="{{ $renewOpen ? route('employees.renew-contract', $renewFlash['id']) : '' }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="from_list" value="1">
                        <div>
                            <label for="lm_renew_number" class="block text-sm font-medium text-gray-700">Nomor Kontrak Baru <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <input id="lm_renew_number" name="contract_number" value="{{ old('contract_number') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @error('contract_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lm_renew_type" class="block text-sm font-medium text-gray-700">Jenis Kontrak <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="lm_renew_type" name="contract_type" required data-contract-type-toggle="#lm_renew_end" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @foreach ($contractTypes as $type)
                                    <option value="{{ $type }}" @selected(old('contract_type', 'PKWT') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('contract_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="lm_renew_start" class="block text-sm font-medium text-gray-700">Mulai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                <input id="lm_renew_start" name="start_date" type="date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @error('start_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="lm_renew_end" class="block text-sm font-medium text-gray-700">Selesai</label>
                                <input id="lm_renew_end" name="end_date" type="date" value="{{ old('end_date') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @error('end_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="lm_renew_notes" class="block text-sm font-medium text-gray-700">Catatan</label>
                            <textarea id="lm_renew_notes" name="notes" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('notes') }}</textarea>
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" data-modal-close class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                            <button type="submit" data-renew-submit class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">{{ $renewMode === 'reactivate' ? 'Aktifkan Kembali & Simpan' : 'Simpan Kontrak Baru' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Proses karyawan keluar langsung dari daftar --}}
            <div data-list-modal="exit" @unless ($exitOpen) hidden @endunless class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
                <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <h2 class="text-base font-semibold text-gray-950">Proses Karyawan Keluar<span class="font-normal text-gray-500" data-exit-name>{{ $exitOpen ? ' — '.$exitFlash['name'] : '' }}</span></h2>
                    <p class="mt-1 text-sm text-gray-500">Karyawan akan dinonaktifkan dan akun login dimatikan. Lengkapi data berikut.</p>
                    <form method="POST" data-list-exit-form data-no-confirm="true" action="{{ $exitOpen ? route('employees.resign', $exitFlash['id']) : '' }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="lm_exit_reason" class="block text-sm font-medium text-gray-700">Alasan Keluar <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="lm_exit_reason" name="exit_reason" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @foreach ($exitReasons as $reason => $label)
                                    <option value="{{ $reason }}" @selected(old('exit_reason', 'resigned') === $reason)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('exit_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lm_exit_date" class="block text-sm font-medium text-gray-700">Tanggal Keluar <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <input id="lm_exit_date" name="exit_date" type="date" value="{{ old('exit_date', now()->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @error('exit_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lm_exit_notes" class="block text-sm font-medium text-gray-700">Catatan Keluar</label>
                            <textarea id="lm_exit_notes" name="exit_notes" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('exit_notes') }}</textarea>
                            @error('exit_notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" data-modal-close class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                            <button type="submit" class="rounded-md border border-red-200 bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Proses Keluar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
</x-layouts.app>
