<x-layouts.app title="Tukar Jadwal - {{ config('app.name', 'HRIS') }}" heading="Persetujuan Tukar Jadwal">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Peninjauan HR</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Persetujuan Tukar Jadwal</h1>
                <p class="mt-1 text-sm text-gray-500">Permintaan yang sudah disetujui rekan. Menyetujui akan menerapkan perubahan ke jadwal.</p>
            </div>
            @if ($pendingCount > 0)<x-status-badge tone="warning">{{ $pendingCount }} menunggu HR</x-status-badge>@endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['pending_hr' => 'Menunggu HR', 'pending_partner' => 'Menunggu Rekan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $value => $label)
                    <a href="{{ route('attendance.swaps.index', array_merge(request()->except('page'), ['status' => $value])) }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => $status === $value, 'border border-gray-200 text-gray-700 hover:bg-gray-50' => $status !== $value])>{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('attendance.swaps.index') }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-medium text-gray-600">Cari pengaju</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nama / NIK" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="branch_id" class="block text-xs font-medium text-gray-600">Lokasi pengaju</label>
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($filters['branchId'] === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-xs font-medium text-gray-600">Dari tanggal</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['dateFrom'] }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-medium text-gray-600">Sampai tanggal</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['dateTo'] }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('attendance.swaps.index', ['status' => $status]) }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </section>

        @php $canDecide = auth()->user()->can('swaps.update'); @endphp
        <section data-approve-scope class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($canDecide)
                <div data-approve-bar hidden class="flex flex-col gap-3 border-b border-primary/20 bg-primary-soft px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-gray-800"><span data-approve-count>0</span> permintaan dipilih</p>
                    <div class="flex items-center gap-2">
                        <button type="button" data-approve-submit class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-emerald-700"><x-icon name="user-check" class="size-4"/> Setujui terpilih</button>
                        <button type="button" data-approve-clear class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Bersihkan</button>
                    </div>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr>@if ($canDecide)<th class="w-10"><input type="checkbox" data-approve-all aria-label="Pilih semua" class="size-4 rounded border-gray-300 text-primary focus:ring-primary/30"></th>@endif<th>Jenis</th><th>Pengaju</th><th>Rekan</th><th>Tanggal</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($requests as $req)
                            <tr>
                                @if ($canDecide)
                                    <td>
                                        @if ($req->isPendingHr())
                                            <input type="checkbox" data-approve-checkbox value="{{ $req->id }}" aria-label="Pilih permintaan {{ $req->requester?->full_name }}" class="size-4 rounded border-gray-300 text-primary focus:ring-primary/30">
                                        @endif
                                    </td>
                                @endif
                                <td class="text-sm text-gray-800">{{ $req->type_label }}@if ($req->reason)<p class="mt-0.5 max-w-[16rem] truncate text-xs text-gray-500" title="{{ $req->reason }}">{{ $req->reason }}</p>@endif</td>
                                <td class="text-sm text-gray-700">{{ $req->requester?->full_name }}</td>
                                <td class="text-sm text-gray-700">{{ $req->partner?->full_name }}</td>
                                <td class="text-sm text-gray-600">{{ $req->requester_date->translatedFormat('d M') }}@if ($req->partner_date) ⇄ {{ $req->partner_date->translatedFormat('d M') }}@endif</td>
                                <td>
                                    <x-status-badge :tone="$req->status_tone">{{ $req->status_label }}</x-status-badge>
                                    @if ($req->reviewer)<p class="mt-1 text-xs text-gray-400">oleh {{ $req->reviewer->name }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @can('swaps.update')
                                        @if ($req->isPendingHr())
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('attendance.swaps.approve', $req) }}" onsubmit="return confirm('Setujui & terapkan tukar jadwal?')">@csrf @method('PATCH')<button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button></form>
                                                <form method="POST" action="{{ route('attendance.swaps.reject', $req) }}">@csrf @method('PATCH')<button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $req->decided_at?->format('d M H:i') }}</span>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canDecide ? 7 : 6 }}" class="cell-empty">Tidak ada permintaan tukar jadwal.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $requests->links() }}</div>

            @if ($canDecide)
                <form data-approve-form method="POST" action="{{ route('attendance.swaps.bulk-approve') }}" class="hidden" data-confirm-message="Setujui & terapkan semua tukar jadwal terpilih?" data-confirm-approve="Ya, setujui">
                    @csrf
                    <div data-approve-ids></div>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
