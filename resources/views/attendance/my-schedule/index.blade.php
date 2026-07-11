<x-layouts.app title="Tukar Jadwal - {{ config('app.name', 'HRIS') }}" heading="Tukar Jadwal">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Self-service</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Jadwal &amp; Tukar Jadwal</h1>
                <p class="mt-1 text-sm text-gray-500">Jadwal 14 hari ke depan. Ajukan tukar shift, ambil-alih, atau tukar libur dengan rekan.</p>
            </div>
            <button type="button" data-open-swap class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Ajukan Tukar</button>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        {{-- Partner requests awaiting my response --}}
        @if ($pendingForMe->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm">
                <div class="border-b border-amber-200 px-5 py-3"><h2 class="text-sm font-semibold text-amber-900">Perlu Respons Anda ({{ $pendingForMe->count() }})</h2></div>
                <div class="divide-y divide-amber-100">
                    @foreach ($pendingForMe as $req)
                        <div class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm">
                                <p class="font-medium text-gray-900">{{ $req->requester?->full_name }} — {{ $req->type_label }}</p>
                                <p class="text-xs text-gray-600">Tgl dia: {{ $req->requester_date->translatedFormat('D, d M') }}@if ($req->partner_date) · tgl Anda: {{ $req->partner_date->translatedFormat('D, d M') }}@endif @if ($req->reason)· "{{ $req->reason }}"@endif</p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('my-schedule.swaps.respond', $req) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="accept"><button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Terima</button></form>
                                <form method="POST" action="{{ route('my-schedule.swaps.respond', $req) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- My schedule --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Jadwal Saya (14 hari)</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Shift</th><th>Jam</th></tr></thead>
                    <tbody>
                        @forelse ($schedule as $row)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $row->work_date->translatedFormat('D, d M Y') }}</td>
                                <td class="text-sm">@if ($row->is_day_off || ! $row->shift)<span class="text-gray-400">Libur</span>@else<span class="font-medium text-gray-900">{{ $row->shift->code }}</span> <span class="text-gray-500">{{ $row->shift->name }}</span>@endif</td>
                                <td class="text-sm text-gray-500">{{ $row->shift && ! $row->is_day_off ? $row->shift->time_range_label : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cell-empty">Belum ada jadwal 14 hari ke depan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- My requests --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Pengajuan Saya</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Jenis</th><th>Rekan</th><th>Tanggal</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($myRequests as $req)
                            <tr>
                                <td class="text-sm text-gray-800">{{ $req->type_label }}</td>
                                <td class="text-sm text-gray-700">{{ $req->partner?->full_name }}</td>
                                <td class="text-sm text-gray-600">{{ $req->requester_date->translatedFormat('d M') }}@if ($req->partner_date) ⇄ {{ $req->partner_date->translatedFormat('d M') }}@endif</td>
                                <td>
                                    <x-status-badge :tone="$req->status_tone">{{ $req->status_label }}</x-status-badge>
                                    @if ($req->status === 'rejected' && $req->decision_notes)<p class="mt-1 text-xs text-gray-400">{{ $req->decision_notes }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @if ($req->isPendingPartner() || $req->isPendingHr())
                                        <form method="POST" action="{{ route('my-schedule.swaps.cancel', $req) }}" onsubmit="return confirm('Batalkan pengajuan?')">@csrf @method('DELETE')<button class="text-sm text-red-600 hover:text-red-700">Batalkan</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Belum ada pengajuan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <dialog id="swap-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
        <form method="POST" action="{{ route('my-schedule.swaps.store') }}" data-no-confirm="true" class="space-y-4 p-6">
            @csrf
            <div>
                <h3 class="text-base font-semibold text-gray-950">Ajukan Tukar Jadwal</h3>
                <p class="mt-1 text-sm text-gray-500">Rekan harus menyetujui, lalu HR menyetujui.</p>
            </div>
            @error('partner_id')<div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>@enderror
            <div>
                <label for="sw-type" class="block text-sm font-medium text-gray-700">Jenis <span class="field-requirement is-required">*</span></label>
                <select name="type" id="sw-type" data-swap-type class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sw-partner" class="block text-sm font-medium text-gray-700">Rekan <span class="field-requirement is-required">*</span></label>
                <select name="partner_id" id="sw-partner" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Pilih rekan…</option>
                    @foreach ($colleagues as $c)
                        <option value="{{ $c->id }}" @selected(old('partner_id') == $c->id)>{{ $c->full_name }} @if ($c->employee_number)({{ $c->employee_number }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="sw-rdate" class="block text-sm font-medium text-gray-700">Tanggal Anda <span class="field-requirement is-required">*</span></label>
                    <input type="date" name="requester_date" id="sw-rdate" min="{{ now()->toDateString() }}" value="{{ old('requester_date') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div data-partner-date-field>
                    <label for="sw-pdate" class="block text-sm font-medium text-gray-700">Tanggal Rekan</label>
                    <input type="date" name="partner_date" id="sw-pdate" min="{{ now()->toDateString() }}" value="{{ old('partner_date') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            <div>
                <label for="sw-reason" class="block text-sm font-medium text-gray-700">Alasan</label>
                <textarea name="reason" id="sw-reason" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('reason') }}</textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-close-dialog class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Kirim</button>
            </div>
        </form>
    </dialog>

    @push('scripts')
    <script>
        (function () {
            const dialog = document.getElementById('swap-dialog');
            if (!dialog) return;
            const type = dialog.querySelector('[data-swap-type]');
            const partnerDateField = dialog.querySelector('[data-partner-date-field]');
            const partnerDate = document.getElementById('sw-pdate');

            function syncType() {
                const isCover = type.value === 'cover';
                partnerDateField.style.display = isCover ? 'none' : '';
                partnerDate.disabled = isCover;
            }

            document.querySelector('[data-open-swap]')?.addEventListener('click', () => dialog.showModal());
            dialog.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());
            type.addEventListener('change', syncType);
            syncType();
            @if ($errors->any()) dialog.showModal(); @endif
        })();
    </script>
    @endpush
</x-layouts.app>
