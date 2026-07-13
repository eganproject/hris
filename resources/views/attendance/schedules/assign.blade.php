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
                    <h3 class="text-sm font-semibold text-gray-950">Karyawan <span class="field-requirement is-required">*</span></h3>
                    <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" data-check-all class="size-4 rounded border-gray-300 text-primary focus:ring-primary"> Pilih semua</label>
                </div>
                <p class="mt-1 text-xs text-gray-500">Periode jadwal yang sudah berjalan atau akan datang ditampilkan di bawah tiap nama. Periode yang bentrok dengan tanggal di atas ditandai merah.</p>
                @error('employee_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="mt-3 max-h-96 overflow-y-auto rounded-md border border-gray-200">
                    @forelse ($employees as $employee)
                        <label class="flex items-start gap-3 border-b border-gray-100 px-4 py-3 text-sm last:border-b-0 hover:bg-gray-50">
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
                </div>
            </section>

            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tugaskan & Buat Jadwal</button>
        </form>
    </div>

    @push('scripts')
    <script>
        document.querySelector('[data-check-all]')?.addEventListener('change', function (e) {
            document.querySelectorAll('[data-employee-check]').forEach(function (c) { c.checked = e.target.checked; });
        });

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
