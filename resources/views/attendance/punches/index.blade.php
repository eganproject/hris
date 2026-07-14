<x-layouts.app title="Log Punch - {{ config('app.name', 'HRIS') }}" heading="Log Punch">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Data mentah dari mesin</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Log Punch</h1>
                <p class="mt-1 text-sm text-gray-500">Setiap tap sidik jari yang diterima. Punch yang belum cocok bisa dipetakan ke karyawan.</p>
            </div>
            <a href="{{ route('attendance.devices.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Perangkat</a>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        {{-- Unmatched PIN queue --}}
        @if ($unmatchedPins->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm">
                <div class="border-b border-amber-200 px-5 py-3"><h2 class="text-sm font-semibold text-amber-900">PIN Belum Dipetakan ({{ $unmatchedPins->count() }})</h2></div>
                <div class="divide-y divide-amber-100">
                    @foreach ($unmatchedPins as $row)
                        <form method="POST" action="{{ route('attendance.punches.assign') }}" class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center">
                            @csrf
                            <input type="hidden" name="machine_user_id" value="{{ $row->machine_user_id }}">
                            <input type="hidden" name="device_id" value="{{ $row->device_id }}">
                            <div class="sm:w-64">
                                <p class="text-sm font-semibold text-gray-900">PIN {{ $row->machine_user_id }}</p>
                                <p class="text-xs text-gray-500">{{ $row->device?->name ?? 'Tanpa device' }} · {{ $row->total }} punch · terakhir {{ \Illuminate\Support\Carbon::parse($row->last_seen)->diffForHumans() }}</p>
                            </div>
                            <select name="employee_id" required class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Pilih karyawan…</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }} @if ($employee->employee_number)({{ $employee->employee_number }})@endif</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Petakan</button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Filter --}}
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.punches.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[200px_200px_auto_auto]">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (['all' => 'Semua', 'matched' => 'Cocok', 'unmatched' => 'Belum cocok', 'ignored' => 'Diabaikan'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="device_id" class="block text-sm font-medium text-gray-700">Perangkat</label>
                    <select id="device_id" name="device_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected(request()->integer('device_id') === $device->id)>{{ $device->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="mt-auto rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('attendance.punches.index') }}" class="mt-auto rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Waktu</th><th>PIN</th><th>Karyawan</th><th>Perangkat</th><th>Verifikasi</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($punches as $punch)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $punch->punched_at->format('d M Y H:i:s') }}</td>
                                <td class="font-mono text-sm text-gray-700">{{ $punch->machine_user_id }}</td>
                                <td class="text-sm text-gray-700">{{ $punch->employee?->full_name ?? '—' }}</td>
                                <td class="text-sm text-gray-600">{{ $punch->device?->name ?? '—' }}</td>
                                <td class="text-xs text-gray-500">{{ $punch->verify_label }}</td>
                                <td>
                                    @php $tone = ['matched' => 'success', 'unmatched' => 'warning', 'ignored' => 'neutral'][$punch->status] ?? 'neutral'; @endphp
                                    <x-status-badge :tone="$tone">{{ ['matched' => 'Cocok', 'unmatched' => 'Belum cocok', 'ignored' => 'Diabaikan'][$punch->status] ?? $punch->status }}</x-status-badge>
                                </td>
                                <td class="text-right">
                                    @can('punches.update')
                                        @if ($punch->status === 'unmatched')
                                            <form method="POST" action="{{ route('attendance.punches.ignore', $punch) }}">@csrf<button type="submit" class="text-xs text-gray-500 hover:text-gray-700">Abaikan</button></form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada punch diterima.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $punches->links() }}</div>
        </section>
    </div>
</x-layouts.app>
