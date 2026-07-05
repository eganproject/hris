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
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total Karyawan</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['total']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Karyawan Aktif</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['active']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Tidak Bekerja</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['inactive']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Lokasi Aktif</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['locations']) }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Kontrak Habis 30 Hari</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950">{{ number_format($summary['expiring_contracts']) }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_200px_200px_170px_190px_130px_auto]">
                <div>
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
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                        Filter
                    </button>
                    <a href="{{ route('employees.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
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
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Karyawan</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Lokasi</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Jabatan</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Kontrak</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-5 py-3 text-right font-medium text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="px-5 py-4">
                                    @if ($employee->photo_url)
                                        <img src="{{ $employee->photo_url }}" alt="Foto {{ $employee->full_name }}" class="mb-2 size-10 rounded-md border border-gray-200 object-cover">
                                    @else
                                        <div class="mb-2 flex size-10 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-sm font-semibold text-gray-500">
                                            {{ str($employee->full_name ?: 'K')->substr(0, 1)->upper() }}
                                        </div>
                                    @endif
                                    <a href="{{ route('employees.show', $employee) }}" class="font-medium text-gray-950 hover:underline">
                                        {{ $employee->full_name ?? 'Tanpa nama' }}
                                    </a>
                                    <p class="mt-1 text-xs text-gray-500">{{ $employee->employee_number ?? 'Belum ada NIK' }} · {{ $employee->email ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    <p class="font-medium text-gray-800">{{ $employee->branch?->name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $employee->branch?->city ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    <p>{{ $employee->jobPosition?->name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $employee->department?->name ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    @if ($employee->currentContract)
                                        <p class="font-medium text-gray-800">{{ $employee->currentContract->contract_type }} · {{ $employee->currentContract->contract_number }}</p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ $employee->currentContract->start_date?->format('d M Y') }} - {{ $employee->currentContract->end_date?->format('d M Y') ?? 'Tidak terbatas' }}
                                        </p>
                                        @if (! is_null($employee->remaining_contract_days))
                                            <p @class([
                                                'mt-1 text-xs font-medium',
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
                                <td class="px-5 py-4">
                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                                        {{ $employee->employment_status_label }}
                                    </span>
                                    @if ($employee->isInactive())
                                        <p class="mt-1 text-xs text-gray-500">{{ $employee->exit_reason_label ?? 'Alasan belum diisi' }}</p>
                                        @if ($employee->exit_date)
                                            <p class="mt-1 text-xs text-gray-500">Keluar {{ $employee->exit_date->format('d M Y') }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                                            Detail
                                        </a>
                                        @can('employees.update')
                                            <a href="{{ route('employees.edit', $employee) }}" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                                                Edit
                                            </a>
                                        @endcan
                                        @can('employees.delete')
                                            <form method="POST" action="{{ route('employees.destroy', $employee) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada data karyawan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $employees->links() }}
            </div>
        </section>
    </div>
</x-layouts.app>
