<x-layouts.app title="Jenis Cuti - {{ config('app.name', 'HRIS') }}" heading="Jenis Cuti">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master data cuti</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Jenis Cuti</h1>
                <p class="mt-1 text-sm text-gray-500">Kelola jenis cuti/izin, kuota default, dan status absensinya.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('attendance.update')
                    <a href="{{ route('attendance.leave-balances.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">Kuota per Karyawan</a>
                @endcan
                @can('attendance.create')
                    <a href="{{ route('attendance.leave-types.create') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah Jenis Cuti</a>
                @endcan
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Kode</th><th>Nama</th><th>Status Absensi</th><th class="text-center">Kuota</th><th class="text-center">Berbayar</th><th class="text-center">Aktif</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($leaveTypes as $type)
                            <tr>
                                <td class="text-sm font-medium text-gray-900">{{ $type->code }}</td>
                                <td class="text-sm text-gray-800">{{ $type->name }}</td>
                                <td><x-status-badge :tone="$type->attendance_status->tone()">{{ $type->attendance_status->label() }}</x-status-badge></td>
                                <td class="text-center text-sm text-gray-700">{{ $type->counts_against_balance ? ($type->default_quota_days ?? 0).' hari' : '—' }}</td>
                                <td class="text-center text-sm">{{ $type->is_paid ? 'Ya' : 'Tidak' }}</td>
                                <td class="text-center">
                                    @if ($type->is_active)
                                        <x-status-badge tone="success">Aktif</x-status-badge>
                                    @else
                                        <x-status-badge tone="neutral">Nonaktif</x-status-badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        @can('attendance.update')
                                            <a href="{{ route('attendance.leave-types.edit', $type) }}" class="text-sm text-gray-600 hover:text-primary">Edit</a>
                                        @endcan
                                        @can('attendance.delete')
                                            <form method="POST" action="{{ route('attendance.leave-types.destroy', $type) }}" onsubmit="return confirm('Hapus jenis cuti ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-700">Hapus</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada jenis cuti. Tambahkan yang pertama.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
