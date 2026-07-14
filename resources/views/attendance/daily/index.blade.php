<x-layouts.app title="Absensi Harian - {{ config('app.name', 'HRIS') }}" heading="Absensi Harian">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Absensi · {{ $date->translatedFormat('l, d F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Absensi Harian</h1>
                <p class="mt-1 text-sm text-gray-500">Status dihitung dari jadwal, hari libur, cuti disetujui, dan jam presensi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('attendance-daily.update')
                    <form method="POST" action="{{ route('attendance.daily.process') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover"><x-icon name="refresh"/> Proses Absensi</button>
                    </form>
                @endcan
            </div>
        </section>

        @if ($hasNoScope)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Cakupan akses Anda belum diatur, jadi belum ada data yang bisa ditampilkan. Minta admin menetapkan lokasi kerja / divisi Anda di menu <span class="font-medium">Kontrol Akses</span>.
            </div>
        @endif

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        {{-- Date navigation + branch filter --}}
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.daily.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.daily.index', ['date' => $prevDate, 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Hari sebelumnya">‹</a>
                    <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('attendance.daily.index', ['date' => $nextDate, 'branch_id' => $branchId]) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Hari berikutnya">›</a>
                </div>
                <select name="branch_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Semua lokasi</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        </section>

        {{-- Summary strip --}}
        <section class="flex flex-wrap gap-2">
            @forelse ($summary as $statusValue => $count)
                @php $status = \App\Enums\AttendanceStatus::from($statusValue); @endphp
                <x-status-badge :tone="$status->tone()">{{ $status->label() }}: {{ $count }}</x-status-badge>
            @empty
                <p class="text-sm text-gray-500">Belum ada absensi diproses untuk tanggal ini. Klik <span class="font-medium">Proses Absensi</span>.</p>
            @endforelse
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Jadwal</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php
                                $att = $employee->attendances->first();
                                $sched = $employee->schedules->first();
                                $schedLabel = $sched ? ($sched->is_day_off ? 'Libur' : ($sched->shift?->code ?? '—')) : 'Belum dijadwalkan';
                            @endphp
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $employee->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $employee->employee_number }}</p></td>
                                <td class="text-sm text-gray-600">{{ $schedLabel }}@if ($sched && ! $sched->is_day_off && $sched->shift)<span class="ml-1 text-xs text-gray-400">{{ $sched->shift->time_range_label }}</span>@endif</td>
                                <td class="text-sm {{ $att && $att->late_minutes > 0 ? 'text-amber-600 font-medium' : 'text-gray-700' }}">{{ $att?->clock_in_label ?? '–' }}</td>
                                <td class="text-sm text-gray-700">{{ $att?->clock_out_label ?? '–' }}</td>
                                <td class="text-sm text-gray-600">{{ $att && $att->late_minutes > 0 ? $att->late_minutes.'m' : '–' }}</td>
                                <td class="text-sm text-gray-600">{{ $att && $att->overtime_minutes > 0 ? floor($att->overtime_minutes / 60).'j '.($att->overtime_minutes % 60).'m' : '–' }}</td>
                                <td>
                                    @if ($att)
                                        <x-status-badge :tone="$att->status->tone()">{{ $att->status->label() }}</x-status-badge>
                                    @else
                                        <span class="text-xs text-gray-400">Belum diproses</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @can('attendance-daily.update')
                                        <button type="button" data-punch
                                            data-emp="{{ $employee->id }}" data-emp-name="{{ $employee->full_name }}"
                                            data-in="{{ $att?->clock_in?->format('H:i') }}" data-out="{{ $att?->clock_out?->format('H:i') }}"
                                            data-note="{{ $att?->note }}"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"><x-icon name="clock"/> Jam</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="cell-empty">Tidak ada karyawan aktif pada lokasi ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @can('attendance-daily.update')
        <dialog id="punch-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
            <form method="POST" action="{{ route('attendance.daily.punch') }}" data-no-confirm="true" class="space-y-4 p-6">
                @csrf
                <input type="hidden" name="employee_id" id="pn-emp">
                <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                <div>
                    <h3 class="text-base font-semibold text-gray-950">Input Jam Presensi</h3>
                    <p class="mt-1 text-sm text-gray-500"><span id="pn-emp-name" class="font-medium text-gray-700"></span> · {{ $date->translatedFormat('d M Y') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="pn-in" class="block text-sm font-medium text-gray-700">Jam Masuk</label>
                        <input type="time" name="clock_in" id="pn-in" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label for="pn-out" class="block text-sm font-medium text-gray-700">Jam Pulang</label>
                        <input type="time" name="clock_out" id="pn-out" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <p class="text-xs text-gray-500">Kosongkan keduanya lalu simpan untuk menandai tidak hadir sesuai jadwal. Jam pulang lebih awal dari jam masuk dianggap lintas tengah malam.</p>
                <div>
                    <label for="pn-note" class="block text-sm font-medium text-gray-700">Catatan <span class="text-gray-400">(opsional)</span></label>
                    <input type="text" name="note" id="pn-note" maxlength="255" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-close-dialog class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Simpan</button>
                </div>
            </form>
        </dialog>

        @push('scripts')
        <script>
            (function () {
                const dialog = document.getElementById('punch-dialog');
                if (!dialog) return;
                const emp = document.getElementById('pn-emp');
                const empName = document.getElementById('pn-emp-name');
                const clockIn = document.getElementById('pn-in');
                const clockOut = document.getElementById('pn-out');
                const note = document.getElementById('pn-note');

                document.querySelectorAll('[data-punch]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        emp.value = btn.dataset.emp;
                        empName.textContent = btn.dataset.empName;
                        clockIn.value = btn.dataset.in || '';
                        clockOut.value = btn.dataset.out || '';
                        note.value = btn.dataset.note || '';
                        dialog.showModal();
                    });
                });

                dialog.querySelector('[data-close-dialog]').addEventListener('click', function () { dialog.close(); });
            })();
        </script>
        @endpush
    @endcan
</x-layouts.app>
