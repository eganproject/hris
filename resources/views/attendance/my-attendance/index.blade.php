<x-layouts.app title="Absensi Saya - {{ config('app.name', 'HRIS') }}" heading="Absensi Saya">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Self-service</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Absensi Saya</h1>
                <p class="mt-1 text-sm text-gray-500">Riwayat absensi 30 hari terakhir. Ajukan koreksi bila ada jam yang salah/terlewat.</p>
            </div>
            <button type="button" data-open-correction class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Ajukan Koreksi</button>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        {{-- Attendance history --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Riwayat Absensi</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($attendances as $att)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $att->work_date->translatedFormat('D, d M Y') }}</td>
                                <td class="text-sm text-gray-600">{{ $att->shift?->code ?? '—' }}</td>
                                <td class="text-sm {{ $att->late_minutes > 0 ? 'font-medium text-amber-600' : 'text-gray-700' }}">{{ $att->clock_in_label }}</td>
                                <td class="text-sm text-gray-700">{{ $att->clock_out_label }}</td>
                                <td class="text-sm text-gray-600">{{ $att->late_minutes > 0 ? $att->late_minutes.'m' : '—' }}</td>
                                <td class="text-sm text-gray-600">{{ $att->overtime_minutes > 0 ? floor($att->overtime_minutes / 60).'j '.($att->overtime_minutes % 60).'m' : '—' }}</td>
                                <td><x-status-badge :tone="$att->status->tone()">{{ $att->status->label() }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada data absensi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- My corrections --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Pengajuan Koreksi Saya</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Usulan Jam</th><th>Alasan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($corrections as $c)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $c->work_date->translatedFormat('d M Y') }}</td>
                                <td class="text-sm text-gray-700">{{ $c->requested_clock_in ?? '—' }} / {{ $c->requested_clock_out ?? '—' }}</td>
                                <td class="max-w-xs truncate text-sm text-gray-600" title="{{ $c->reason }}">{{ $c->reason }}</td>
                                <td>
                                    <x-status-badge :tone="$c->status_tone">{{ $c->status_label }}</x-status-badge>
                                    @if ($c->status === 'rejected' && $c->decision_notes)<p class="mt-1 text-xs text-gray-400">{{ $c->decision_notes }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @if ($c->isPending())
                                        <form method="POST" action="{{ route('my-attendance.corrections.cancel', $c) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:text-red-700">Batalkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Belum ada pengajuan koreksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <dialog id="correction-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
        <form method="POST" action="{{ route('my-attendance.corrections.store') }}" class="space-y-4 p-6">
            @csrf
            <div>
                <h3 class="text-base font-semibold text-gray-950">Ajukan Koreksi Absensi</h3>
                <p class="mt-1 text-sm text-gray-500">Isi jam yang seharusnya. HR akan meninjau pengajuan Anda.</p>
            </div>
            <div>
                <label for="cor-date" class="block text-sm font-medium text-gray-700">Tanggal <span class="field-requirement is-required">*</span></label>
                <input type="date" name="work_date" id="cor-date" max="{{ now()->toDateString() }}" value="{{ old('work_date') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('work_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="cor-in" class="block text-sm font-medium text-gray-700">Jam Masuk</label>
                    <input type="time" name="requested_clock_in" id="cor-in" value="{{ old('requested_clock_in') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="cor-out" class="block text-sm font-medium text-gray-700">Jam Pulang</label>
                    <input type="time" name="requested_clock_out" id="cor-out" value="{{ old('requested_clock_out') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            @error('requested_clock_in')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div>
                <label for="cor-reason" class="block text-sm font-medium text-gray-700">Alasan <span class="field-requirement is-required">*</span></label>
                <textarea name="reason" id="cor-reason" rows="3" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. Lupa tap saat pulang.">{{ old('reason') }}</textarea>
                @error('reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
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
            const dialog = document.getElementById('correction-dialog');
            if (!dialog) return;
            document.querySelector('[data-open-correction]')?.addEventListener('click', () => dialog.showModal());
            dialog.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());
            @if ($errors->any()) dialog.showModal(); @endif
        })();
    </script>
    @endpush
</x-layouts.app>
