<x-layouts.app title="Monitor Mesin - {{ config('app.name', 'HRIS') }}" heading="Monitor Mesin">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Komunikasi mesin sidik jari</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Monitor Mesin</h1>
                <p class="mt-1 text-sm text-gray-500">Status koneksi &amp; log interaksi iclock (handshake, kirim absensi, polling).</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" id="auto-refresh" class="size-4 rounded border-gray-300 text-primary focus:ring-primary"> Auto-refresh 15s</label>
                <a href="{{ route('attendance.devices.monitor') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="refresh"/> Segarkan</a>
                <a href="{{ route('attendance.devices.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kelola Perangkat</a>
            </div>
        </section>

        {{-- Summary --}}
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Total Mesin</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950">{{ $devices->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Online</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $onlineCount }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Offline / Nonaktif</p>
                <p class="mt-1 text-2xl font-semibold text-gray-400">{{ $devices->count() - $onlineCount }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Punch Hari Ini</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950">{{ number_format($punchesToday->sum()) }}</p>
            </div>
        </section>

        <p class="text-xs text-gray-400">Mesin dianggap <span class="font-medium text-emerald-600">Online</span> bila menghubungi server dalam {{ $onlineWithin }} menit terakhir. Diperbarui {{ now()->format('H:i:s') }}.</p>

        {{-- Devices status --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Status Perangkat</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Mesin</th><th>Lokasi</th><th>Status</th><th>Terakhir Kontak</th><th>IP</th><th>Aktivitas Terakhir</th><th>Punch Hari Ini</th></tr></thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $device->name }}</p><p class="mt-0.5 font-mono text-xs text-gray-500">{{ $device->serial_number }}</p></td>
                                <td class="text-sm text-gray-600">{{ $device->branch?->name ?? '—' }}</td>
                                <td>
                                    <x-status-badge :tone="$device->status_tone">
                                        @if ($device->is_active && $device->isOnline())<span class="relative flex size-2"><span class="absolute inline-flex size-2 animate-ping rounded-full bg-emerald-400 opacity-75"></span></span>@endif
                                        {{ $device->status_label }}
                                    </x-status-badge>
                                </td>
                                <td class="text-sm text-gray-600" title="{{ $device->last_seen_at?->format('d M Y H:i:s') }}">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum pernah' }}</td>
                                <td class="font-mono text-xs text-gray-500">{{ $device->last_ip ?? '—' }}</td>
                                <td class="text-sm">
                                    @if ($device->latestCommunication)
                                        <x-status-badge :tone="$device->latestCommunication->event_tone">{{ $device->latestCommunication->event_label }}</x-status-badge>
                                        <span class="ml-1 text-xs text-gray-400">{{ $device->latestCommunication->created_at->format('H:i:s') }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="text-sm text-gray-700">{{ number_format($punchesToday[$device->id] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada perangkat terdaftar. <a href="{{ route('attendance.devices.create') }}" class="text-primary">Daftarkan mesin</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Communication log --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Log Komunikasi Terbaru</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Waktu</th><th>Mesin</th><th>Peristiwa</th><th>Data</th><th>IP</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $log)
                            <tr>
                                <td class="whitespace-nowrap text-sm text-gray-700">{{ $log->created_at->format('d M H:i:s') }}</td>
                                <td class="text-sm text-gray-700">{{ $log->device?->name ?? '—' }}</td>
                                <td><x-status-badge :tone="$log->event_tone">{{ $log->event_label }}</x-status-badge></td>
                                <td class="text-sm text-gray-600">{{ $log->event === 'attlog' ? $log->records_count.' punch' : '—' }}</td>
                                <td class="font-mono text-xs text-gray-500">{{ $log->ip ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Belum ada komunikasi tercatat. Pastikan mesin sudah dikonfigurasi menembak ke server ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        (function () {
            const cb = document.getElementById('auto-refresh');
            if (!cb) return;
            const KEY = 'device-monitor-autorefresh';
            let timer;

            cb.checked = localStorage.getItem(KEY) !== '0';

            function schedule() {
                clearTimeout(timer);
                if (cb.checked) {
                    timer = setTimeout(function () { location.reload(); }, 15000);
                }
            }

            cb.addEventListener('change', function () {
                localStorage.setItem(KEY, cb.checked ? '1' : '0');
                schedule();
            });

            schedule();
        })();
    </script>
    @endpush
</x-layouts.app>
