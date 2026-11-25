<x-layouts.app title="Absen Aplikasi HP - {{ config('app.name', 'HRIS') }}" heading="Absen Aplikasi HP">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Absensi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Absen Aplikasi HP</h1>
                <p class="mt-1 text-sm text-gray-500">Absen selfie dari aplikasi HP. Absen yang ditandai (di luar radius, lokasi tidak terbaca, terkirim terlambat) tetap tercatat di Absensi Harian dan menunggu tinjauan di sini.</p>
            </div>
            @if ($reviewCount > 0)<x-status-badge tone="warning">{{ $reviewCount }} perlu ditinjau</x-status-badge>@endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @include('attendance.mobile._tabs', ['active' => $tab])

            <form method="GET" action="{{ route('attendance.mobile.index') }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-medium text-gray-600">Cari karyawan</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nama / NIK" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="date" class="block text-xs font-medium text-gray-600">Tanggal</label>
                    <input type="date" id="date" name="date" value="{{ $filters['date'] }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex gap-2 lg:col-span-3 lg:justify-end">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('attendance.mobile.index', ['tab' => $tab]) }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Selfie</th><th>Karyawan</th><th>Waktu</th><th>Lokasi</th><th>Keterangan</th><th class="text-right">Tinjauan</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>
                                    @if ($log->photo_path)
                                        <a href="{{ route('attendance.mobile.photo', $log) }}" target="_blank" rel="noopener" title="Buka foto">
                                            <img src="{{ route('attendance.mobile.photo', $log) }}" alt="Selfie {{ $log->employee?->full_name }}" class="size-8 rounded object-cover ring-1 ring-gray-200 transition hover:ring-primary">
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-300">—</span>
                                    @endif
                                </td>
                                <td><p class="font-medium text-gray-950">{{ $log->employee?->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $log->employee?->employee_number }} · {{ $log->device?->model ?? 'HP' }}</p></td>
                                <td class="text-sm text-gray-700">
                                    <p class="font-medium">{{ $log->type === 'in' ? 'Masuk' : 'Pulang' }} {{ $log->captured_at->format('H:i') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $log->captured_at->translatedFormat('d M Y') }}@if ($log->received_at->diffInMinutes($log->captured_at, true) > 5) · terkirim {{ $log->received_at->format('d/m H:i') }}@endif</p>
                                </td>
                                <td class="text-sm text-gray-700">
                                    <p>{{ $log->mode_label }}@if ($log->mode === 'office' && $log->branch) · {{ $log->branch->name }}@endif</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        @if ($log->distance_m !== null){{ number_format($log->distance_m, 0, ',', '.') }} m dari titik @endif
                                        @if ($log->accuracy_m !== null)(±{{ $log->accuracy_m }} m)@endif
                                        @if ($log->wifi_matched) · Wi-Fi kantor @endif
                                        @if ($log->mapUrl())<a href="{{ $log->mapUrl() }}" target="_blank" rel="noopener" class="text-primary hover:underline">peta</a>@endif
                                    </p>
                                </td>
                                <td class="text-sm">
                                    @if (! $log->isAccepted())
                                        <x-status-badge tone="danger">Ditolak</x-status-badge>
                                        <p class="mt-1 max-w-xs text-xs text-gray-600">{{ $log->reject_reason }}</p>
                                    @elseif ($log->flags)
                                        <div class="flex max-w-xs flex-wrap gap-1">
                                            @foreach ($log->flagLabels() as $flag)
                                                <x-status-badge tone="warning">{{ $flag }}</x-status-badge>
                                            @endforeach
                                        </div>
                                    @else
                                        <x-status-badge tone="success">Sesuai</x-status-badge>
                                    @endif
                                    @if ($log->employee_note)<p class="mt-1 max-w-xs text-xs text-gray-600">Alasan: “{{ $log->employee_note }}”</p>@endif
                                </td>
                                <td class="text-right">
                                    @if ($log->review_status === 'pending')
                                        @can('mobile-attendance.update')
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('attendance.mobile.review', $log) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approved"><button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Terima</button></form>
                                                <form method="POST" action="{{ route('attendance.mobile.review', $log) }}" onsubmit="return confirm('Tandai absen ini ditolak? Jam di Absensi Harian tidak berubah otomatis.')">@csrf @method('PATCH')<input type="hidden" name="decision" value="rejected"><button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                                            </div>
                                        @else
                                            <x-status-badge tone="warning">Menunggu</x-status-badge>
                                        @endcan
                                    @elseif ($log->review_status)
                                        <x-status-badge :tone="$log->review_status === 'approved' ? 'success' : 'danger'">{{ $log->review_status === 'approved' ? 'Diterima' : 'Ditolak' }}</x-status-badge>
                                        @if ($log->reviewer)<p class="mt-1 text-xs text-gray-400">oleh {{ $log->reviewer->name }}</p>@endif
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">{{ $tab === 'review' ? 'Tidak ada absen yang perlu ditinjau.' : 'Belum ada absen dari aplikasi.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $logs->links() }}</div>
        </section>
    </div>
</x-layouts.app>
