<x-layouts.app title="Aktivitas Pengguna - {{ config('app.name', 'HRIS') }}" heading="Aktivitas Pengguna">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">System</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Aktivitas Pengguna</h1>
                <p class="mt-1 text-sm text-gray-500">Siapa melakukan apa, kapan, dan dari mana. Catatan ini hanya bisa dibaca — tidak bisa disunting atau dihapus dari sini.</p>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['Aktivitas 24 jam', $stats['total'], 'Seluruh catatan', 'neutral'],
                ['Pengguna aktif', $stats['users'], 'Akun yang tercatat', 'info'],
                ['Perubahan data', $stats['changes'], 'Tambah · ubah · hapus', 'success'],
                ['Gagal masuk', $stats['failed_logins'], 'Termasuk yang diblokir', 'danger'],
            ] as [$label, $value, $hint, $tone])
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p @class([
                        'mt-1.5 text-2xl font-semibold',
                        'text-red-600' => $tone === 'danger' && $value > 0,
                        'text-gray-950' => ! ($tone === 'danger' && $value > 0),
                    ])>{{ number_format($value) }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">{{ $hint }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('activity.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label for="act_search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="act_search" name="search" value="{{ $filters['search'] }}" placeholder="Keterangan, objek, nama, atau IP" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="act_user" class="block text-sm font-medium text-gray-700">Pengguna</label>
                    <select id="act_user" name="user_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua pengguna</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($filters['user_id'] === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="act_module" class="block text-sm font-medium text-gray-700">Modul</label>
                    <select id="act_module" name="module" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua modul</option>
                        @foreach ($modules as $key => $label)
                            <option value="{{ $key }}" @selected($filters['module'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="act_event" class="block text-sm font-medium text-gray-700">Jenis</label>
                    <select id="act_event" name="event" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua jenis</option>
                        @foreach ($events as $key => $label)
                            <option value="{{ $key }}" @selected($filters['event'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="act_per_page" class="block text-sm font-medium text-gray-700">Per halaman</label>
                    <select id="act_per_page" name="per_page" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} / halaman</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="act_from" class="block text-sm font-medium text-gray-700">Dari tanggal</label>
                    <input id="act_from" type="date" name="from" value="{{ $filters['from'] }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="act_to" class="block text-sm font-medium text-gray-700">Sampai tanggal</label>
                    <input id="act_to" type="date" name="to" value="{{ $filters['to'] }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white">Terapkan</button>
                    <a href="{{ route('activity.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Jenis</th><th>Keterangan</th><th>Alamat IP</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="align-top">
                                <td class="whitespace-nowrap">
                                    <p class="font-medium text-gray-900">{{ $log->created_at?->translatedFormat('d M Y') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $log->created_at?->format('H:i:s') }}</p>
                                </td>
                                <td>
                                    <p class="font-medium text-gray-900">{{ $log->actor_label }}</p>
                                    @if ($log->user?->email)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $log->user->email }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-gray-600">{{ $log->module_label }}</td>
                                <td><x-status-badge :tone="$log->event_tone">{{ $log->event_label }}</x-status-badge></td>
                                <td>
                                    <p class="text-gray-800">{{ $log->description }}</p>
                                    @php $changes = $log->change_summary; @endphp
                                    @if ($changes !== [])
                                        {{-- Rincian perubahan dilipat: satu baris audit bisa memuat belasan
                                             kolom, dan membentangkan semuanya membuat tabel tak terbaca. --}}
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-[11px] font-medium text-primary">{{ count($changes) }} kolom berubah</summary>
                                            <ul class="mt-1 space-y-0.5 text-[11px] text-gray-500">
                                                @foreach ($changes as $line)
                                                    <li class="font-mono">{{ $line }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-gray-500">
                                    {{ $log->ip_address ?? '—' }}
                                    @if ($log->user_agent)
                                        <p class="mt-0.5 max-w-[220px] truncate text-[11px] text-gray-400" title="{{ $log->user_agent }}">{{ $log->user_agent }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada aktivitas yang cocok dengan penyaring ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $logs->links() }}</div>
        </section>

        <p class="text-xs text-gray-400">
            Catatan yang lebih lama dari 180 hari dipangkas otomatis setiap awal bulan agar tabelnya tetap ringan.
        </p>
    </div>
</x-layouts.app>
