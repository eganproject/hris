<x-layouts.app title="Jadwal Kerja - {{ config('app.name', 'HRIS') }}" heading="Jadwal Kerja">
    <div class="mx-auto max-w-full space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Roster · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Jadwal Kerja</h1>
                <p class="mt-1 text-sm text-gray-500">Jadwal harian hasil pola. Klik satu sel untuk ubah manual.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('attendance.schedule-patterns.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Pola Jadwal</a>
                @can('schedules.import')
                    <button type="button" data-open-import class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                        <x-icon name="upload" class="size-4"/> Import Excel
                    </button>
                @endcan
                @can('schedules.update')
                    {{-- Carries every filter, so regenerating covers exactly the rows on screen. --}}
                    <form method="POST" action="{{ route('attendance.schedules.generate') }}" data-generate-form data-no-confirm="true">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <input type="hidden" name="branch_id" value="{{ $filters['branch_id'] }}">
                        <input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">
                        <input type="hidden" name="job_position_id" value="{{ $filters['job_position_id'] }}">
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60"><x-icon name="refresh"/> Generate Ulang</button>
                    </form>
                @endcan
                @can('schedules.create')<a href="{{ route('attendance.schedules.assign') }}" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Tugaskan Pola</a>@endcan
            </div>
        </section>

        <x-scope-notice :has-no-scope="$hasNoScope" :has-no-team="$hasNoTeam"/>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($patternCount === 0)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada pola jadwal. <a href="{{ route('attendance.schedule-patterns.create') }}" class="font-medium underline">Buat pola dulu</a>, lalu tugaskan ke karyawan.
            </div>
        @endif

        {{-- Month navigation + filters --}}
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @php
                // Navigasi bulan & reset harus mempertahankan tab dan ukuran halaman,
                // tapi selalu kembali ke halaman 1 (baris yang ditampilkan berubah).
                $q = fn ($extra = []) => array_merge(
                    ['month' => $month->format('Y-m'), 'tab' => $tab, 'per_page' => $perPage],
                    array_filter($filters),
                    $extra,
                );
            @endphp
            <form method="GET" action="{{ route('attendance.schedules.index') }}" class="flex flex-col gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('attendance.schedules.index', $q(['month' => $prevMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Bulan sebelumnya">‹</a>
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <a href="{{ route('attendance.schedules.index', $q(['month' => $nextMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Bulan berikutnya">›</a>
                    </div>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama / kode karyawan" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:w-64">
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                    <select name="branch_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="department_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    <select name="job_position_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua jabatan</option>
                        @foreach ($jobPositions as $position)
                            <option value="{{ $position->id }}" @selected($filters['job_position_id'] === $position->id)>{{ $position->name }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Terapkan</button>
                        @if (array_filter($filters))
                            <a href="{{ route('attendance.schedules.index', ['month' => $month->format('Y-m'), 'tab' => $tab, 'per_page' => $perPage]) }}" class="rounded-md border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                        @endif
                    </div>
                </div>
            </form>
        </section>

        {{-- Roster (kalender) dan daftar penugasan dipisah ke dua tab: menumpuknya
             memaksa pengguna menggulir melewati sebulan penuh jadwal hanya untuk
             sampai ke yang kedua. Tab dipilih di server, jadi yang tidak dibuka tidak
             ikut dimuat sama sekali. --}}
        @php
            $tabLink = fn (string $target) => route('attendance.schedules.index', array_merge(
                ['month' => $month->format('Y-m'), 'per_page' => $perPage],
                array_filter($filters),
                ['tab' => $target],
            ));

            $tabs = [
                'roster' => ['label' => 'Roster Bulanan', 'count' => $employeeCount],
                'assignments' => ['label' => 'Penugasan Pola', 'count' => $assignmentCount],
            ];
        @endphp
        {{-- Nav dan panelnya dikelompokkan sendiri supaya terbaca sebagai satu unit,
             bukan dua kartu terpisah seperti jarak default halaman ini. --}}
        <div class="space-y-3">
        <nav class="flex items-end gap-1 border-b border-gray-200" aria-label="Tampilan jadwal">
            @foreach ($tabs as $key => $meta)
                <a href="{{ $tabLink($key) }}"
                    @if ($tab === $key) aria-current="page" @endif
                    @class([
                        '-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition',
                        'border-primary text-primary' => $tab === $key,
                        'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $tab !== $key,
                    ])>
                    {{ $meta['label'] }}
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums',
                        'bg-primary/10 text-primary' => $tab === $key,
                        'bg-gray-100 text-gray-600' => $tab !== $key,
                    ])>{{ number_format($meta['count']) }}</span>
                </a>
            @endforeach
        </nav>

        @if ($tab === 'roster')
        {{-- Roster grid --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            {{-- Tinggi dibatasi supaya halaman tidak ikut memanjang mengikuti jumlah
                 baris; baris tanggal dan kolom nama menempel saat digulir. --}}
            <div class="max-h-[clamp(280px,56vh,640px)] overflow-auto" data-roster-grid>
                <table class="w-full border-collapse text-center text-xs">
                    <thead>
                        <tr>
                            {{-- Sel yang menempel butuh latar sendiri: latar baris tidak
                                 ikut tercetak di bawahnya. Garis bawahnya pakai inset
                                 shadow karena border-collapse tidak menggambar border
                                 pada sel sticky. --}}
                            <th class="sticky left-0 top-0 z-30 min-w-[180px] border-r border-gray-200 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-700 shadow-[inset_0_-1px_0_#e5e7eb]">Karyawan</th>
                            @foreach ($days as $day)
                                @php
                                    $hol = $holidays[$day->toDateString()] ?? null;
                                    $rest = $hol || $day->isWeekend();
                                @endphp
                                <th @class([
                                        'sticky top-0 z-20 min-w-[38px] px-1 py-1.5 font-medium shadow-[inset_0_-1px_0_#e5e7eb]',
                                        'bg-red-50 text-red-600' => $rest,
                                        'bg-gray-50 text-gray-500' => ! $rest,
                                    ]) title="{{ $hol?->name }}">
                                    <div class="text-[10px] uppercase">{{ $day->translatedFormat('D') }}</div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $day->format('d') }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php
                                $map = $employee->schedules->keyBy(fn ($s) => $s->work_date->toDateString());
                                $employeeLeaves = $leaves[$employee->id] ?? collect();
                            @endphp
                            <tr class="border-b border-gray-100 last:border-b-0">
                                <td class="sticky left-0 z-10 border-r border-gray-200 bg-white px-3 py-1.5 text-left">
                                    <a href="{{ route('attendance.schedules.show', ['employee' => $employee, 'month' => $month->format('Y-m')]) }}" class="font-medium text-gray-900 hover:text-primary hover:underline">{{ $employee->full_name }}</a>
                                    @if ($employee->follows_office_hours)
                                        <span class="ml-1 inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 align-middle" title="Mengikuti pola jam kantor tanpa penjadwalan">Jam kantor</span>
                                    @endif
                                    <p class="text-[11px] text-gray-500">{{ $employee->employee_number }}</p>
                                </td>
                                @foreach ($days as $day)
                                    @php
                                        $key = $day->toDateString();
                                        $sched = $map[$key] ?? null;
                                        $hol = $holidays[$key] ?? null;
                                        $leave = $employeeLeaves[$key] ?? null;
                                    @endphp
                                    <td data-cell-slot="{{ $employee->id }}|{{ $key }}" @class(['border-l border-gray-100 p-0.5', 'bg-red-50/60' => $hol, 'bg-gray-50/60' => ! $hol && $day->isWeekend()])>
                                        @include('attendance.schedules._cell', ['employee' => $employee, 'day' => $day, 'sched' => $sched, 'leave' => $leave])
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ $days->count() + 1 }}" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada karyawan aktif pada lokasi ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Keterangan warna dilipat: cepat jadi hafalan, dan melipatnya
                 mengembalikan ruang layar untuk gridnya sendiri. --}}
            <details class="border-t border-gray-200 px-4 py-2.5">
                <summary class="cursor-pointer text-[11px] font-medium text-gray-600 transition hover:text-gray-800">Keterangan warna &amp; cara pakai</summary>
                <div class="mt-2.5 flex flex-wrap items-center gap-4 text-[11px] text-gray-500">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-primary/10 ring-1 ring-primary/20"></span> Ada shift (kode)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-indigo-100 ring-1 ring-indigo-200"></span> WFH (🏠) — tetap bekerja</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-blue-100 ring-1 ring-blue-200"></span> Dinas luar (DL) — tetap bekerja</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-amber-100 ring-1 ring-amber-200"></span> Cuti/izin disetujui (kode jenis)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded text-gray-300">—</span> Libur</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded ring-1 ring-blue-400"></span> Override manual</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-red-50"></span> Hari libur nasional</span>
                    <span class="ml-auto">Klik satu sel untuk ubah manual, atau klik nama karyawan untuk jadwal per karyawan.</span>
                </div>
            </details>

            @include('attendance.schedules._paginator', ['paginator' => $employees, 'unit' => 'karyawan'])
        </section>
        @else
        {{-- Active assignments --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Penugasan Pola Aktif</h2>
                {{-- Hindari menyebut label statusnya persis: ada tes yang menghitung
                     berapa baris berstatus mana dari isi halaman. --}}
                <p class="mt-0.5 text-xs text-gray-500">Pola yang menyentuh {{ $month->translatedFormat('F Y') }}, terbaru di atas. Penugasan yang seluruh harinya sudah diambil alih penugasan lebih baru ditandai pada kolom Status.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Pola</th><th>Periode</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody data-assignments-body>
                        @forelse ($assignments as $assignment)
                            <tr data-assignment-row>
                                <td class="font-medium text-gray-900">{{ $assignment->employee?->full_name }}</td>
                                <td>{{ $assignment->pattern?->name }} <span class="text-xs text-gray-500">({{ $assignment->pattern?->type->label() }})</span></td>
                                <td class="text-sm text-gray-600">{{ $assignment->start_date->translatedFormat('d M Y') }} – {{ $assignment->end_date ? $assignment->end_date->translatedFormat('d M Y') : 'seterusnya' }}</td>
                                <td>
                                    @if (isset($governingAssignments[$assignment->id]))
                                        <x-status-badge tone="success">Berlaku</x-status-badge>
                                    @else
                                        <x-status-badge tone="neutral" title="Seluruh harinya sudah diambil alih penugasan yang lebih baru">Tergantikan</x-status-badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @can('schedules.delete')
                                        <form method="POST" action="{{ route('attendance.schedules.assignments.destroy', $assignment) }}" data-assignment-delete data-no-confirm="true">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-700 disabled:opacity-60"><x-icon name="trash"/> Hapus</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr data-assignments-empty><td colspan="5" class="cell-empty">Belum ada penugasan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('attendance.schedules._paginator', ['paginator' => $assignments, 'unit' => 'penugasan'])
        </section>
        @endif
        </div>
    </div>

    @can('schedules.update')
        {{-- Hanya tab roster yang punya sel untuk diklik; import tetap tersedia di
             kedua tab karena tombolnya ada di header halaman. --}}
        @if ($tab === 'roster')
        <dialog id="override-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
            <form method="POST" action="{{ route('attendance.schedules.override') }}" data-override-form data-no-confirm="true" class="space-y-4 p-6">
                @csrf
                <input type="hidden" name="employee_id" id="ov-employee-id">
                <input type="hidden" name="work_date" id="ov-work-date">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                <div>
                    <h3 class="text-base font-semibold text-gray-950">Ubah Jadwal Harian</h3>
                    <p class="mt-1 text-sm text-gray-500"><span id="ov-emp-name" class="font-medium text-gray-700"></span> · <span id="ov-date-label"></span></p>
                    <p id="ov-leave" hidden class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Karyawan ini sudah disetujui <span id="ov-leave-type" class="font-semibold"></span> pada tanggal tersebut.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" name="is_day_off" value="1" id="ov-day-off" class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                    Tandai sebagai libur
                </label>
                <div id="ov-shift-wrap">
                    <label for="ov-shift" class="block text-sm font-medium text-gray-700">Shift</label>
                    <select name="shift_id" id="ov-shift" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih shift…</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->code }} — {{ $shift->name }}</option>
                        @endforeach
                    </select>
                    <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_wfh" value="1" id="ov-wfh" class="size-4 rounded border-gray-300 text-primary focus:ring-primary">
                        Kerja dari rumah (WFH) — jam kerja tetap dihitung
                    </label>
                </div>
                <div>
                    <label for="ov-note" class="block text-sm font-medium text-gray-700">Catatan <span class="text-gray-400">(opsional)</span></label>
                    <input type="text" name="note" id="ov-note" maxlength="255" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-close-dialog class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Simpan</button>
                </div>
            </form>
        </dialog>
        @endif

        {{-- Import roster bulanan dari Excel --}}
        @php
            $importErrors = session('import_errors', []);
            $templateQuery = array_merge(['month' => $month->format('Y-m')], array_filter($filters));
        @endphp
        <div data-import-modal @unless ($importErrors) hidden @endunless class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
            <div class="w-full max-w-lg rounded-lg border border-gray-200 bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="schedule-import-title">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5">
                    <div>
                        <h2 id="schedule-import-title" class="text-base font-semibold text-gray-950">Import Jadwal dari Excel</h2>
                        <p class="mt-1 text-sm text-gray-500">Isi roster satu bulan penuh dalam satu file: satu baris per karyawan, satu kolom per tanggal.</p>
                    </div>
                    <button type="button" data-import-close class="-m-1 rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" aria-label="Tutup">&times;</button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    @if ($importErrors)
                        <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3">
                            <p class="text-sm font-semibold text-red-800">Import dibatalkan — perbaiki {{ count($importErrors) }} masalah berikut. Tidak ada jadwal yang tersimpan.</p>
                            <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto pr-1 text-xs text-red-700">
                                @foreach ($importErrors as $importError)
                                    <li>• {{ $importError }}</li>
                                @endforeach
                            </ul>
                            @if ($importErrorToken = session('import_error_token'))
                                {{-- Jalan pulihnya: file yang sama dikembalikan bertanda,
                                     jadi tinggal diperbaiki lalu diunggah lagi. --}}
                                <a href="{{ route('attendance.schedules.import.errors', $importErrorToken) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-red-700">
                                    <x-icon name="download" class="size-3.5"/> Unduh File Perbaikan
                                </a>
                                <p class="mt-1.5 text-xs text-red-600">File yang Anda unggah dikembalikan apa adanya, ditambah kolom “Kesalahan” di ujung kanan dan sheet “Kesalahan” berisi rincian tiap masalah. Sel yang bermasalah ditandai merah; masalah yang menyangkut seluruh file langsung terbuka di sheet “Kesalahan”. Perbaiki di file itu juga, lalu unggah ulang.</p>
                            @endif
                        </div>
                    @endif

                    <ol class="space-y-3 text-sm text-gray-600">
                        <li class="flex gap-3">
                            <span class="flex size-6 flex-none items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-gray-700">1</span>
                            <span>
                                Unduh template. File sudah berisi <strong>daftar karyawan sesuai filter di halaman ini</strong> beserta jadwal {{ $month->translatedFormat('F Y') }} yang sudah ada, jadi tinggal disesuaikan.
                                <a href="{{ route('attendance.schedules.import.template', $templateQuery) }}" class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                    <x-icon name="download" class="size-3.5"/> Unduh Template {{ $month->translatedFormat('F Y') }}
                                </a>
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-6 flex-none items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-gray-700">2</span>
                            @php
                                $exampleShift = $shifts->first();
                                $exampleHours = $exampleShift
                                    ? substr((string) $exampleShift->start_time, 0, 5).'-'.substr((string) $exampleShift->end_time, 0, 5)
                                    : '08:00-17:00';
                            @endphp
                            <span>Pada sheet <strong>“Jadwal”</strong>, isi tiap sel dengan <strong>kode shift</strong> (mis. <code class="rounded bg-gray-100 px-1">{{ $exampleShift?->code ?? 'P' }}</code>) <strong>atau jam kerjanya</strong> (mis. <code class="rounded bg-gray-100 px-1">{{ $exampleHours }}</code>), <strong>L</strong> untuk libur, atau tambahkan <code class="rounded bg-gray-100 px-1">/WFH</code> di belakang keduanya untuk kerja dari rumah. <strong>Sel kosong tidak diubah.</strong> Jam yang ditulis harus sama persis dengan salah satu shift aktif.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-6 flex-none items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-gray-700">3</span>
                            <span>Bulan yang diimpor mengikuti sel <strong>B1 (“Periode”)</strong> di dalam file, bukan bulan yang sedang dibuka. Sheet <strong>“Petunjuk”</strong> memuat daftar kode shift dan aturan lengkapnya.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-6 flex-none items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-gray-700">4</span>
                            <span>Unggah file di bawah. Jika ada <strong>satu sel saja</strong> yang salah, seluruh import dibatalkan — tidak ada jadwal setengah jadi.</span>
                        </li>
                    </ol>

                    <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">Hari yang diisi menjadi <strong>jadwal manual</strong> dan tidak akan ditimpa lagi oleh generator roster otomatis. Untuk tanggal yang sudah lewat dan absensinya sudah diproses, status absensi dihitung ulang mengikuti jadwal baru — jam presensi yang sudah tercatat tetap dipertahankan.</p>

                    <form method="POST" action="{{ route('attendance.schedules.import') }}" enctype="multipart/form-data" data-no-confirm="true" data-loading-title="Mengimpor jadwal..." data-loading-message="Memvalidasi dan menyimpan roster." class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $filters['branch_id'] }}">
                        <input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">
                        <input type="hidden" name="job_position_id" value="{{ $filters['job_position_id'] }}">
                        <div>
                            <label for="schedule-import-file" class="block text-sm font-medium text-gray-700">File Excel (.xlsx, .xls, .csv) <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <input id="schedule-import-file" name="file" type="file" accept=".xlsx,.xls,.csv" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-xs outline-none file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @error('file')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" data-import-close class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                                <x-icon name="upload" class="size-4"/> Import Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
            (function () {
                const modal = document.querySelector('[data-import-modal]');
                if (!modal) return;

                const open = () => { modal.hidden = false; };
                const close = () => { modal.hidden = true; };

                document.querySelectorAll('[data-open-import]').forEach((button) => button.addEventListener('click', open));
                modal.querySelectorAll('[data-import-close]').forEach((button) => button.addEventListener('click', close));
                modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
                document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) close(); });
            })();
        </script>
        @endpush
    @endcan
</x-layouts.app>
