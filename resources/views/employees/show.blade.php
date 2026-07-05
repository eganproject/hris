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

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Status Karyawan</p>
                <p class="mt-2 text-xl font-semibold text-gray-950">{{ $employee->employment_status_label }}</p>
            </article>
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
                    'text-red-600' => ! is_null($employee->remaining_contract_days) && $employee->remaining_contract_days <= 30,
                    'text-gray-950' => is_null($employee->remaining_contract_days) || $employee->remaining_contract_days > 30,
                ])>
                    @if (is_null($employee->remaining_contract_days))
                        Tidak terbatas
                    @elseif ($employee->remaining_contract_days >= 0)
                        {{ $employee->remaining_contract_days }} hari
                    @else
                        Berakhir
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
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500">Nomor</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500">Jenis</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500">Periode</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($employee->contracts as $contract)
                                    <tr>
                                        <td class="px-5 py-4 font-medium text-gray-950">{{ $contract->contract_number }}</td>
                                        <td class="px-5 py-4 text-gray-600">{{ $contract->contract_type }}</td>
                                        <td class="px-5 py-4 text-gray-600">
                                            {{ $contract->start_date?->format('d M Y') }} - {{ $contract->end_date?->format('d M Y') ?? 'Tidak terbatas' }}
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ str($contract->status)->headline() }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-gray-500">Belum ada kontrak.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
                            <dt class="text-gray-500">Departemen</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->department?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Jabatan</dt>
                            <dd class="mt-1 font-medium text-gray-950">{{ $employee->jobPosition?->name ?? '-' }}</dd>
                        </div>
                    </dl>
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
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-base font-semibold text-gray-950">Status Akhir Karyawan</h2>

                        @if ($employee->isInactive())
                            <dl class="mt-5 space-y-4 text-sm">
                                <div>
                                    <dt class="text-gray-500">Status Akhir</dt>
                                    <dd class="mt-1 font-medium text-gray-950">{{ $employee->employment_status_label }}</dd>
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
