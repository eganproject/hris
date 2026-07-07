<x-layouts.app title="Edit Perangkat - {{ config('app.name', 'HRIS') }}" heading="Edit Perangkat">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-950">Edit Perangkat</h1>
            <a href="{{ route('attendance.devices.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('attendance.devices.update', $device) }}" class="space-y-6">
            @csrf @method('PUT')
            @include('attendance.devices._form')
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan Perubahan</button>
        </form>

        {{-- PIN → employee mapping --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Pemetaan PIN Karyawan</h2>
                <p class="mt-0.5 text-xs text-gray-500">Hubungkan PIN yang terdaftar di mesin dengan karyawan. Punch lama yang belum cocok akan dihitung ulang otomatis.</p>
            </div>
            <form method="POST" action="{{ route('attendance.devices.mappings.store', $device) }}" class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-end">
                @csrf
                <div class="sm:w-40">
                    <label for="machine_user_id" class="block text-sm font-medium text-gray-700">PIN di mesin</label>
                    <input id="machine_user_id" name="machine_user_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. 17">
                </div>
                <div class="flex-1">
                    <label for="employee_id" class="block text-sm font-medium text-gray-700">Karyawan</label>
                    <select id="employee_id" name="employee_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih karyawan…</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }} @if ($employee->employee_number)({{ $employee->employee_number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">Tambah</button>
            </form>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>PIN</th><th>Karyawan</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($device->mappings as $mapping)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $mapping->machine_user_id }}</td>
                                <td>{{ $mapping->employee?->full_name ?? '—' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('attendance.devices.mappings.destroy', $mapping) }}" onsubmit="return confirm('Hapus pemetaan ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-700"><x-icon name="trash"/> Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cell-empty">Belum ada PIN dipetakan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
