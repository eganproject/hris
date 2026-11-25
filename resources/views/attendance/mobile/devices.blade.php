<x-layouts.app title="HP Terdaftar - {{ config('app.name', 'HRIS') }}" heading="Absen Aplikasi HP">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Absen Aplikasi HP</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">HP Terdaftar</h1>
                <p class="mt-1 text-sm text-gray-500">Satu karyawan satu HP. HP pertama aktif otomatis; ganti HP perlu persetujuan di sini, dan HP lama langsung tidak bisa dipakai.</p>
            </div>
            @if ($pendingDevices > 0)<x-status-badge tone="warning">{{ $pendingDevices }} menunggu</x-status-badge>@endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @include('attendance.mobile._tabs', ['active' => 'devices'])

            <form method="GET" action="{{ route('attendance.mobile.devices') }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-600">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (['pending' => 'Menunggu persetujuan', 'active' => 'Aktif', 'rejected' => 'Ditolak', 'revoked' => 'Dicabut', 'all' => 'Semua'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-medium text-gray-600">Cari karyawan</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nama / NIK" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex gap-2 lg:col-span-3 lg:justify-end">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>HP</th><th>Status</th><th>Terakhir dipakai</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($devices as $device)
                            @php $current = $activeFor->get($device->employee_id); @endphp
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $device->employee?->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $device->employee?->employee_number }} · {{ $device->employee?->branch?->name }}</p></td>
                                <td class="text-sm text-gray-700">
                                    <p>{{ $device->model ?? 'HP' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ ucfirst($device->platform) }} {{ $device->os_version }} · aplikasi {{ $device->app_version ?? '-' }}</p>
                                    @if ($device->status === 'pending' && $current)
                                        <p class="mt-1 text-xs text-amber-700">Mengganti: {{ $current->model ?? 'HP' }} (aktif sejak {{ $current->decided_at?->translatedFormat('d M Y') ?? $current->created_at->translatedFormat('d M Y') }})</p>
                                    @endif
                                </td>
                                <td>
                                    <x-status-badge :tone="['active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'revoked' => 'neutral'][$device->status] ?? 'neutral'">{{ $device->status_label }}</x-status-badge>
                                    @if ($device->decision_note)<p class="mt-1 max-w-xs text-xs text-gray-500">{{ $device->decision_note }}</p>@endif
                                    @if ($device->decider)<p class="mt-0.5 text-xs text-gray-400">oleh {{ $device->decider->name }}</p>@endif
                                </td>
                                <td class="text-sm text-gray-600">{{ $device->last_seen_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="text-right">
                                    @can('mobile-attendance.update')
                                        <div class="flex justify-end gap-2">
                                            @if (in_array($device->status, ['pending', 'rejected'], true))
                                                <form method="POST" action="{{ route('attendance.mobile.devices.decide', $device) }}" onsubmit="return confirm('Setujui HP ini? HP lama karyawan ini akan dicabut.')">@csrf @method('PATCH')<input type="hidden" name="action" value="approve"><button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button></form>
                                            @endif
                                            @if ($device->status === 'pending')
                                                <form method="POST" action="{{ route('attendance.mobile.devices.decide', $device) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="reject"><button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                                            @endif
                                            @if ($device->status === 'active')
                                                <form method="POST" action="{{ route('attendance.mobile.devices.decide', $device) }}" onsubmit="return confirm('Cabut HP ini? Karyawan tidak bisa absen dari HP ini lagi.')">@csrf @method('PATCH')<input type="hidden" name="action" value="revoke"><button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Cabut</button></form>
                                            @endif
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Tidak ada HP dengan status ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $devices->links() }}</div>
        </section>
    </div>
</x-layouts.app>
