<x-layouts.app title="Karyawan Belum Terjadwal - {{ config('app.name', 'HRIS') }}" heading="Karyawan Belum Terjadwal">
    @php
        $isMonthly = $mode === 'no_schedule';
        $canCreate = auth()->user()?->can('schedules.create');
        // Switch mode while keeping the other filters; reset pagination.
        $modeUrl = fn (string $m) => request()->fullUrlWithQuery(['mode' => $m === 'no_pattern' ? null : $m, 'page' => null]);
        // Keep the current query (incl. month) when moving between months.
        $monthUrl = fn (string $ym) => request()->fullUrlWithQuery(['month' => $ym, 'page' => null]);
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Attendance</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Karyawan Belum Terjadwal</h1>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($isMonthly)
                        Karyawan aktif yang belum punya baris jadwal untuk <span class="font-medium text-gray-700">{{ $month->translatedFormat('F Y') }}</span> — mis. roster bulan itu belum dibuat untuk mereka.
                    @else
                        Karyawan aktif yang belum pernah ditugaskan pola jadwal sama sekali, jadi roster tidak bisa membuat harinya.
                    @endif
                </p>
            </div>

            <a href="{{ route('attendance.unscheduled.export', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                <x-icon name="download" class="size-4"/> Export Excel
            </a>
        </section>

        {{-- Mode switch --}}
        <div class="inline-flex rounded-md border border-gray-200 bg-gray-50 p-0.5 text-sm">
            <a href="{{ $modeUrl('no_pattern') }}" @class(['rounded px-3 py-1.5 font-medium transition', 'bg-white text-gray-950 shadow-xs' => ! $isMonthly, 'text-gray-500 hover:text-gray-800' => $isMonthly])>Belum punya pola</a>
            <a href="{{ $modeUrl('no_schedule') }}" @class(['rounded px-3 py-1.5 font-medium transition', 'bg-white text-gray-950 shadow-xs' => $isMonthly, 'text-gray-500 hover:text-gray-800' => ! $isMonthly])>Belum ada jadwal (bulanan)</a>
        </div>

        @if ($hasNoScope)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Cakupan akses Anda belum diatur, jadi belum ada data karyawan yang bisa ditampilkan. Minta admin menetapkan lokasi kerja / divisi Anda di menu <span class="font-medium">Kontrol Akses</span>.
            </div>
        @endif

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-stat-card :label="$isMonthly ? 'Belum Ada Jadwal '.$month->translatedFormat('M Y') : 'Belum Punya Pola'" :value="number_format($employees->total())" tone="amber" hint="Sesuai filter aktif">
                <x-icon name="user-x" class="size-5"/>
            </x-stat-card>
        </section>

        {{-- Month navigation (monthly mode only) --}}
        @if ($isMonthly)
            <div class="flex items-center gap-2">
                <a href="{{ $monthUrl($prevMonth) }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Bulan sebelumnya">‹</a>
                <span class="min-w-40 text-center text-sm font-semibold text-gray-900">{{ $month->translatedFormat('F Y') }}</span>
                <a href="{{ $monthUrl($nextMonth) }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Bulan berikutnya">›</a>
            </div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.unscheduled.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @if ($isMonthly)
                    <input type="hidden" name="mode" value="no_schedule">
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                @endif
                <div class="xl:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Nama atau kode karyawan">
                </div>
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi</label>
                    <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi</label>
                    <select id="department_id" name="department_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="job_position_id" class="block text-sm font-medium text-gray-700">Jabatan</label>
                    <select id="job_position_id" name="job_position_id" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua jabatan</option>
                        @foreach ($jobPositions as $position)
                            <option value="{{ $position->id }}" @selected(($filters['job_position_id'] ?? '') == $position->id)>{{ $position->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-full flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:w-auto">Filter</button>
                    <a href="{{ route('attendance.unscheduled.index', $isMonthly ? ['mode' => 'no_schedule', 'month' => $month->format('Y-m')] : []) }}" class="w-full rounded-md border border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto">Reset</a>
                </div>
            </form>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Bulk assign: tick people in the table below, then give them one pattern. --}}
        @if ($canCreate && $employees->isNotEmpty())
            {{-- The row checkboxes live in the table and point back here with form=,
                 so the panel stays a self-contained form instead of wrapping it. --}}
            <form id="bulk-assign-form" method="POST" action="{{ route('attendance.unscheduled.assign') }}" data-bulk-form data-no-confirm="true"
                data-loading-title="Menugaskan pola..." data-loading-message="Membuat jadwal untuk karyawan terpilih."
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                {{-- Kembali ke tampilan yang sama setelah selesai. --}}
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input type="hidden" name="branch_id" value="{{ $filters['branch_id'] ?? '' }}">
                <input type="hidden" name="department_id" value="{{ $filters['department_id'] ?? '' }}">
                <input type="hidden" name="job_position_id" value="{{ $filters['job_position_id'] ?? '' }}">
                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">

                <div class="flex flex-col gap-1 border-b border-gray-100 pb-4">
                    <h2 class="text-sm font-semibold text-gray-950">Penjadwalan Massal</h2>
                    <p class="text-sm text-gray-500">Centang karyawan di tabel bawah, lalu tetapkan <span class="font-medium text-gray-700">satu pola yang sama</span> untuk semuanya sekaligus.</p>
                </div>

                @if ($patterns->isEmpty())
                    <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        Belum ada pola jadwal aktif. <a href="{{ route('attendance.schedule-patterns.create') }}" class="font-medium underline">Buat pola dulu</a>.
                    </p>
                @else
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label for="bulk-pattern" class="block text-sm font-medium text-gray-700">Pola jadwal <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="bulk-pattern" name="schedule_pattern_id" required class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Pilih pola…</option>
                                @foreach ($patterns as $pattern)
                                    <option value="{{ $pattern->id }}" @selected(old('schedule_pattern_id') == $pattern->id)>{{ $pattern->name }} ({{ $pattern->type->label() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="bulk-start" class="block text-sm font-medium text-gray-700">Tanggal mulai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <input id="bulk-start" type="date" name="start_date" required value="{{ old('start_date', $defaultStart) }}" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                        <div>
                            <label for="bulk-end" class="block text-sm font-medium text-gray-700">Tanggal selesai <span class="text-gray-400">(opsional)</span></label>
                            <input id="bulk-end" type="date" name="end_date" value="{{ old('end_date') }}" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <p class="mt-1 text-xs text-gray-500">Kosongkan agar berlaku seterusnya.</p>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" data-bulk-submit disabled class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50">
                                Tugaskan ke <span data-bulk-count>0</span> karyawan
                            </button>
                        </div>
                    </div>
                    <p data-bulk-hint class="mt-3 text-xs text-gray-500">Belum ada karyawan dicentang. Pilihan berlaku untuk halaman ini saja.</p>
                @endif
            </form>
        @endif

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            @if ($canCreate && $employees->isNotEmpty())
                                <th class="w-10">
                                    <input type="checkbox" data-bulk-all aria-label="Pilih semua karyawan di halaman ini" class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                                </th>
                            @endif
                            <th>Karyawan</th>
                            <th>Lokasi</th>
                            <th>Divisi</th>
                            <th>Jabatan</th>
                            <th>Tgl Bergabung</th>
                            @if ($isMonthly)
                                <th>Status Pola</th>
                            @endif
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php $hasCoveringPattern = $isMonthly && ($employee->covering_count ?? 0) > 0; @endphp
                            <tr>
                                @if ($canCreate)
                                    <td>
                                        <input type="checkbox" form="bulk-assign-form" name="employee_ids[]" value="{{ $employee->id }}"
                                            data-bulk-item aria-label="Pilih {{ $employee->full_name }}"
                                            class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                                    </td>
                                @endif
                                <td>
                                    <a href="{{ route('attendance.schedules.show', $employee) }}" class="font-medium text-gray-950 hover:underline">{{ $employee->full_name ?? 'Tanpa nama' }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $employee->employee_number ?? 'Kode belum dibuat' }}</p>
                                </td>
                                <td class="text-sm text-gray-700">{{ $employee->branch?->name ?? '-' }}</td>
                                <td class="text-sm text-gray-700">{{ $employee->departments->pluck('name')->implode(', ') ?: '-' }}</td>
                                <td class="text-sm text-gray-700">{{ $employee->jobPosition?->name ?? '-' }}</td>
                                <td class="text-sm text-gray-600">{{ $employee->join_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                @if ($isMonthly)
                                    <td>
                                        @if ($hasCoveringPattern)
                                            <x-status-badge tone="warning">Ada pola · perlu generate</x-status-badge>
                                        @else
                                            <x-status-badge tone="danger">Belum ada pola</x-status-badge>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-right">
                                    @if ($hasCoveringPattern)
                                        <a href="{{ route('attendance.schedules.index', ['month' => $month->format('Y-m'), 'branch_id' => $filters['branch_id'] ?? null]) }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                            <x-icon name="calendar-clock" class="size-3.5"/> Buka Roster
                                        </a>
                                    @elseif ($canCreate)
                                        <a href="{{ route('attendance.schedules.assign', ['employee_id' => $employee->id]) }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                            <x-icon name="calendar-clock" class="size-3.5"/> Tugaskan Pola
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">Perlu ditugaskan pola</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                {{-- No rows means no checkbox column in the header either. --}}
                                <td colspan="{{ $isMonthly ? 7 : 6 }}" class="cell-empty">
                                    @if ($isMonthly)
                                        Semua karyawan sudah punya jadwal untuk {{ $month->translatedFormat('F Y') }} (sesuai filter). 🎉
                                    @else
                                        Tidak ada karyawan yang belum terjadwal untuk filter ini. Semua sudah punya pola. 🎉
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4">
                {{ $employees->links() }}
            </div>
        </section>
    </div>

    @if ($canCreate)
        @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('bulk-assign-form');
                if (!form) return;

                const all = document.querySelector('[data-bulk-all]');
                const items = Array.from(document.querySelectorAll('[data-bulk-item]'));
                const submit = form.querySelector('[data-bulk-submit]');
                const count = form.querySelector('[data-bulk-count]');
                const hint = form.querySelector('[data-bulk-hint]');

                const sync = () => {
                    const selected = items.filter((item) => item.checked).length;

                    if (count) count.textContent = selected;
                    if (submit) submit.disabled = selected === 0;
                    if (hint) {
                        hint.textContent = selected === 0
                            ? 'Belum ada karyawan dicentang. Pilihan berlaku untuk halaman ini saja.'
                            : selected + ' karyawan dipilih. Semuanya akan mendapat pola dan periode yang sama.';
                    }
                    if (all) {
                        all.checked = selected > 0 && selected === items.length;
                        // Partially selected reads as neither on nor off.
                        all.indeterminate = selected > 0 && selected < items.length;
                    }
                };

                if (all) {
                    all.addEventListener('change', () => {
                        items.forEach((item) => { item.checked = all.checked; });
                        sync();
                    });
                }

                items.forEach((item) => item.addEventListener('change', sync));
                sync();
            })();
        </script>
        @endpush
    @endif
</x-layouts.app>
