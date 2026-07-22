<x-layouts.app title="Tugaskan Pola - {{ config('app.name', 'HRIS') }}" heading="Tugaskan Pola Jadwal">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-950">Tugaskan Pola Jadwal</h1>
                <p class="mt-1 text-sm text-gray-500">Pilih karyawan, pola, dan periode. Jadwal harian langsung dibuat otomatis.</p>
            </div>
            <a href="{{ route('attendance.schedules.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
        </div>

        @if ($patterns->isEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada pola jadwal aktif. <a href="{{ route('attendance.schedule-patterns.create') }}" class="font-medium underline">Buat pola dulu</a>.
            </div>
        @endif

        <form method="POST" action="{{ route('attendance.schedules.store') }}" class="space-y-6">
            @csrf
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="schedule_pattern_id" class="block text-sm font-medium text-gray-700">Pola Jadwal <span class="field-requirement is-required">*</span></label>
                        <select id="schedule_pattern_id" name="schedule_pattern_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Pilih pola…</option>
                            @foreach ($patterns as $pattern)
                                <option value="{{ $pattern->id }}" @selected(old('schedule_pattern_id') == $pattern->id)>{{ $pattern->name }} ({{ $pattern->type->label() }})</option>
                            @endforeach
                        </select>
                        @error('schedule_pattern_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div></div>
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai <span class="field-requirement is-required">*</span></label>
                        <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $defaultStart) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @error('start_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Selesai <span class="text-gray-400">(opsional)</span></label>
                        <input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <p class="mt-2 text-xs text-gray-500">Kosongkan untuk berlaku terus-menerus (dibuat {{ \App\Services\ScheduleGenerator::DEFAULT_HORIZON_DAYS }} hari ke depan).</p>
                        @error('end_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-950">Karyawan <span class="field-requirement is-required">*</span> <span data-visible-count class="ml-1 text-xs font-normal text-gray-400"></span></h3>
                    <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" data-check-all class="size-4 rounded border-gray-300 text-primary focus:ring-primary"> Pilih semua <span class="text-gray-400">(yang tampil)</span></label>
                </div>
                <p class="mt-1 text-xs text-gray-500">Periode jadwal yang sudah berjalan atau akan datang ditampilkan di bawah tiap nama. Periode yang bentrok dengan tanggal di atas ditandai merah.</p>
                @error('employee_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                {{-- Filter pemilihan karyawan. Sisi-klien: menyaring daftar tanpa reload,
                     sehingga pola, tanggal, dan centang yang sudah dipilih tidak hilang. --}}
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <input type="text" data-filter-search placeholder="Cari nama / kode" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <select data-filter-branch class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select data-filter-department class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                    <select data-filter-position class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua jabatan</option>
                        @foreach ($jobPositions as $position)
                            <option value="{{ $position->id }}">{{ $position->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-3 max-h-96 overflow-y-auto rounded-md border border-gray-200">
                    @forelse ($employees as $employee)
                        <label data-employee-row
                            data-branch="{{ $employee->branch_id }}"
                            data-position="{{ $employee->job_position_id }}"
                            data-departments="{{ implode(',', $employee->departmentIds()) }}"
                            data-name="{{ strtolower($employee->full_name.' '.$employee->employee_number) }}"
                            class="flex items-start gap-3 border-b border-gray-100 px-4 py-3 text-sm last:border-b-0 hover:bg-gray-50">
                            <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" data-employee-check @checked(collect(old('employee_ids', $selectedEmployee ? [$selectedEmployee] : []))->contains($employee->id)) class="mt-0.5 size-4 shrink-0 rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="font-medium text-gray-900">{{ $employee->full_name }}</span>
                                    <span class="text-xs text-gray-500">{{ $employee->employee_number }}</span>
                                </span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-gray-500">
                                    <span>{{ $employee->jobPosition?->name ?? 'Jabatan belum diisi' }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span>{{ $employee->department?->name ?? 'Divisi belum diisi' }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span>{{ $employee->branch?->name ?? 'Lokasi belum diisi' }}</span>
                                </span>
                                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @forelse ($employee->scheduleAssignments as $assignment)
                                        <span data-assignment
                                            data-start="{{ $assignment->start_date->toDateString() }}"
                                            data-end="{{ $assignment->end_date?->toDateString() }}"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] text-gray-600">
                                            <span class="font-medium text-gray-700">{{ $assignment->pattern?->name ?? 'Pola dihapus' }}</span>
                                            <span class="text-gray-400">·</span>
                                            <span>{{ $assignment->start_date->translatedFormat('d M Y') }} – {{ $assignment->end_date ? $assignment->end_date->translatedFormat('d M Y') : 'seterusnya' }}</span>
                                            <span data-conflict-flag class="hidden font-semibold text-red-600">Bentrok</span>
                                        </span>
                                    @empty
                                        <span class="inline-flex items-center rounded-md border border-dashed border-gray-200 px-2 py-0.5 text-[11px] text-gray-400">Belum ada jadwal</span>
                                    @endforelse
                                </span>
                            </span>
                        </label>
                    @empty
                        <p class="px-4 py-3 text-sm text-gray-500">Tidak ada karyawan aktif.</p>
                    @endforelse
                    <p data-no-match hidden class="px-4 py-3 text-sm text-gray-500">Tidak ada karyawan yang cocok dengan filter.</p>
                </div>
            </section>

            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tugaskan & Buat Jadwal</button>
        </form>
    </div>

    @push('scripts')
    <script>
        (function () {
            const rows = Array.from(document.querySelectorAll('[data-employee-row]'));
            const checkAll = document.querySelector('[data-check-all]');
            const search = document.querySelector('[data-filter-search]');
            const branch = document.querySelector('[data-filter-branch]');
            const department = document.querySelector('[data-filter-department]');
            const position = document.querySelector('[data-filter-position]');
            const noMatch = document.querySelector('[data-no-match]');
            const countEl = document.querySelector('[data-visible-count]');

            const visibleRows = () => rows.filter((r) => !r.hidden);
            const checkbox = (row) => row.querySelector('[data-employee-check]');

            function syncCheckAll() {
                if (!checkAll) return;
                const vis = visibleRows();
                const checked = vis.filter((r) => checkbox(r)?.checked);
                checkAll.checked = vis.length > 0 && checked.length === vis.length;
                checkAll.indeterminate = checked.length > 0 && checked.length < vis.length;
            }

            function apply() {
                const q = (search?.value || '').trim().toLowerCase();
                const b = branch?.value || '';
                const d = department?.value || '';
                const p = position?.value || '';

                rows.forEach((row) => {
                    const depts = (row.dataset.departments || '').split(',').filter(Boolean);
                    const show = (!q || (row.dataset.name || '').includes(q))
                        && (!b || row.dataset.branch === b)
                        && (!d || depts.includes(d))
                        && (!p || row.dataset.position === p);
                    row.hidden = !show;
                    // Baris yang tersembunyi tidak boleh ikut terkirim: hapus centangnya.
                    if (!show && checkbox(row)) checkbox(row).checked = false;
                });

                const vis = visibleRows();
                if (noMatch) noMatch.hidden = vis.length !== 0 || rows.length === 0;
                if (countEl) countEl.textContent = rows.length ? (vis.length + ' dari ' + rows.length + ' karyawan') : '';
                syncCheckAll();
            }

            checkAll?.addEventListener('change', function (e) {
                visibleRows().forEach((row) => { if (checkbox(row)) checkbox(row).checked = e.target.checked; });
            });
            rows.forEach((row) => checkbox(row)?.addEventListener('change', syncCheckAll));
            [search, branch, department, position].forEach((el) => {
                el?.addEventListener('input', apply);
                el?.addEventListener('change', apply);
            });

            apply();
        })();

        (function () {
            const startInput = document.getElementById('start_date');
            const endInput = document.getElementById('end_date');
            const badges = document.querySelectorAll('[data-assignment]');
            const neutral = ['border-gray-200', 'bg-gray-50', 'text-gray-600'];
            const conflict = ['border-red-200', 'bg-red-50', 'text-red-700'];

            function markConflicts() {
                // An open-ended period ("seterusnya") is treated as running forever.
                const start = startInput.value || null;
                const end = endInput.value || null;

                badges.forEach(function (badge) {
                    const badgeStart = badge.dataset.start;
                    const badgeEnd = badge.dataset.end || null;
                    const overlaps = start !== null
                        && (badgeEnd === null || badgeEnd >= start)
                        && (end === null || badgeStart <= end);

                    badge.classList.remove(...(overlaps ? neutral : conflict));
                    badge.classList.add(...(overlaps ? conflict : neutral));
                    badge.querySelector('[data-conflict-flag]').classList.toggle('hidden', ! overlaps);
                });
            }

            startInput.addEventListener('change', markConflicts);
            endInput.addEventListener('change', markConflicts);
            markConflicts();
        })();
    </script>
    @endpush
</x-layouts.app>
