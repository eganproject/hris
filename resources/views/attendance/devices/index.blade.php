<x-layouts.app title="Perangkat Absensi - {{ config('app.name', 'HRIS') }}" heading="Perangkat Absensi">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Integrasi mesin sidik jari</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Perangkat Absensi</h1>
                <p class="mt-1 text-sm text-gray-500">Mesin fingerprint (Solution X100-C) yang mengirim data via protokol iclock.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('attendance.punches.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Log Punch</a>
                @can('attendance.create')<a href="{{ route('attendance.devices.create') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tambah Perangkat</a>@endcan
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <p class="font-medium">Endpoint untuk mesin:</p>
            <p class="mt-1 font-mono text-xs">Server / ADMS: <span class="font-semibold">{{ str_replace(['https://', 'http://'], '', config('app.url')) }}</span> · Port 443 (HTTPS) · Path <span class="font-semibold">/iclock/</span></p>
            <p class="mt-1 text-xs text-blue-700">Aktifkan "Cloud Server / ADMS" di menu Comm mesin, lalu isi alamat server di atas. Pastikan SN mesin terdaftar &amp; aktif.</p>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Perangkat</th><th>Lokasi</th><th>PIN Terpetakan</th><th>Total Punch</th><th>Terakhir Aktif</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $device->name }}</p><p class="mt-0.5 font-mono text-xs text-gray-500">{{ $device->serial_number }}</p></td>
                                <td class="text-sm text-gray-600">{{ $device->branch?->name ?? '—' }}</td>
                                <td class="text-sm text-gray-700">{{ $device->mappings_count }}</td>
                                <td class="text-sm text-gray-700">{{ number_format($device->punches_count) }}</td>
                                <td class="text-sm text-gray-600">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum pernah' }}</td>
                                <td><x-status-badge :tone="$device->is_active ? 'success' : 'neutral'">{{ $device->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge></td>
                                <td class="text-right">
                                    @canany(['attendance.update', 'attendance.delete'])
                                        <x-action-menu>
                                            @can('attendance.update')<a href="{{ route('attendance.devices.edit', $device) }}" class="action-menu-item"><x-icon name="pencil"/> Edit &amp; PIN</a>@endcan
                                            @can('attendance.delete')<form method="POST" action="{{ route('attendance.devices.destroy', $device) }}" onsubmit="return confirm('Hapus perangkat ini?')">@csrf @method('DELETE')<button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button></form>@endcan
                                        </x-action-menu>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada perangkat terdaftar. <a href="{{ route('attendance.devices.create') }}" class="text-primary">Daftarkan mesin pertama</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
