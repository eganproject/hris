<x-layouts.app title="Lembur Saya - {{ config('app.name', 'HRIS') }}" heading="Lembur Saya">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Self-service</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Lembur Saya</h1>
                <p class="mt-1 text-sm text-gray-500">Ajukan lembur untuk hari yang sudah Anda kerjakan. Persetujuan dilakukan oleh atasan langsung Anda.</p>
            </div>
            @if ($hasSupervisor)
                <button type="button" data-open-overtime class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Ajukan Lembur</button>
            @endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @unless ($hasSupervisor)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Atasan langsung Anda belum diatur, sehingga Anda belum bisa mengajukan lembur. Hubungi HR untuk menetapkan atasan Anda.</div>
        @endunless

        {{-- Subordinate requests awaiting my approval (I am their supervisor) --}}
        @if ($pendingForMe->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm">
                <div class="border-b border-amber-200 px-5 py-3"><h2 class="text-sm font-semibold text-amber-900">Perlu Persetujuan Anda ({{ $pendingForMe->count() }})</h2></div>
                <div class="divide-y divide-amber-100">
                    @foreach ($pendingForMe as $req)
                        <div class="flex flex-col gap-3 px-5 py-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="text-sm">
                                <p class="font-medium text-gray-900">{{ $req->employee?->full_name }} <span class="text-gray-500">· {{ $req->employee?->employee_number }}</span></p>
                                <p class="mt-0.5 text-xs text-gray-600">
                                    {{ $req->work_date->translatedFormat('D, d M Y') }} · {{ $req->time_range_label }} ·
                                    <span class="font-medium text-gray-700">{{ intdiv($req->requested_minutes, 60) }}j {{ $req->requested_minutes % 60 }}m</span>
                                    @if ($req->computed_minutes > 0)<span class="text-gray-400">(absensi: {{ intdiv($req->computed_minutes, 60) }}j {{ $req->computed_minutes % 60 }}m)</span>@endif
                                </p>
                                @if ($req->reason)<p class="mt-0.5 text-xs italic text-gray-500">“{{ $req->reason }}”</p>@endif
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('my-overtime.approve', $req) }}" data-no-confirm="true">@csrf @method('PATCH')<button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button></form>
                                <form method="POST" action="{{ route('my-overtime.reject', $req) }}" data-no-confirm="true">@csrf @method('PATCH')<button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Tolak</button></form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- My own overtime requests --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Pengajuan Saya</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Jam</th><th>Durasi</th><th>Atasan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($myRequests as $req)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $req->work_date->translatedFormat('D, d M Y') }}</td>
                                <td class="text-sm text-gray-600">{{ $req->time_range_label ?? '—' }}</td>
                                <td class="text-sm font-medium text-gray-800">
                                    {{ intdiv($req->requested_minutes, 60) }}j {{ $req->requested_minutes % 60 }}m
                                    @if ($req->status === 'approved' && $req->approved_minutes !== $req->requested_minutes)
                                        <span class="text-xs font-normal text-emerald-600">(disetujui {{ intdiv($req->approved_minutes, 60) }}j {{ $req->approved_minutes % 60 }}m)</span>
                                    @endif
                                </td>
                                <td class="text-sm text-gray-600">{{ $req->supervisor?->full_name ?? '—' }}</td>
                                <td>
                                    <x-status-badge :tone="$req->status_tone">{{ $req->status_label }}</x-status-badge>
                                    @if ($req->status === 'rejected' && $req->notes)<p class="mt-1 text-xs text-gray-400">{{ $req->notes }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @if ($req->status === 'pending')
                                        <form method="POST" action="{{ route('my-overtime.cancel', $req) }}" onsubmit="return confirm('Batalkan pengajuan lembur ini?')">@csrf @method('DELETE')<button class="text-sm text-red-600 hover:text-red-700">Batalkan</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada pengajuan lembur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if ($hasSupervisor)
        <dialog id="overtime-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
            <form method="POST" action="{{ route('my-overtime.store') }}" data-no-confirm="true" class="space-y-4 p-6">
                @csrf
                <div>
                    <h3 class="text-base font-semibold text-gray-950">Ajukan Lembur</h3>
                    <p class="mt-1 text-sm text-gray-500">Diajukan ke atasan langsung Anda untuk disetujui.</p>
                </div>

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        <ul class="space-y-0.5">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label for="ot-date" class="block text-sm font-medium text-gray-700">Tanggal Lembur <span class="field-requirement is-required">*</span></label>
                    <input type="date" name="work_date" id="ot-date" max="{{ now()->toDateString() }}" value="{{ old('work_date', now()->toDateString()) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="ot-start" class="block text-sm font-medium text-gray-700">Jam Mulai <span class="field-requirement is-required">*</span></label>
                        <input type="time" name="start_time" id="ot-start" value="{{ old('start_time') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label for="ot-end" class="block text-sm font-medium text-gray-700">Jam Selesai <span class="field-requirement is-required">*</span></label>
                        <input type="time" name="end_time" id="ot-end" value="{{ old('end_time') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <p class="text-xs text-gray-400">Jika lembur melewati tengah malam (mis. 22:00–01:00), sistem menghitungnya otomatis.</p>
                <div>
                    <label for="ot-reason" class="block text-sm font-medium text-gray-700">Uraian Pekerjaan <span class="field-requirement is-required">*</span></label>
                    <textarea name="reason" id="ot-reason" rows="2" required maxlength="500" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Contoh: Menyelesaikan pesanan produksi yang mendesak.">{{ old('reason') }}</textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-close-dialog class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Kirim Pengajuan</button>
                </div>
            </form>
        </dialog>

        @push('scripts')
        <script>
            (function () {
                const dialog = document.getElementById('overtime-dialog');
                if (!dialog) return;

                document.querySelector('[data-open-overtime]')?.addEventListener('click', () => dialog.showModal());
                dialog.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());
                @if ($errors->any()) dialog.showModal(); @endif
            })();
        </script>
        @endpush
    @endif
</x-layouts.app>
