<x-layouts.app title="Dashboard - {{ config('app.name', 'HRIS') }}" heading="Dashboard">
    <div class="mx-auto max-w-7xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Ringkasan</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">{{ now()->translatedFormat('l, d F Y') }}</p>
        </section>

        {{-- Ringkasan pribadi (untuk akun yang tertaut ke karyawan) --}}
        @if ($personal)
            @if ($employeeDashboard)
                @php
                    $today = $personal['today'];
                    $todayAttendance = $today['attendance'];
                    $todaySchedule = $today['schedule'];
                    $todayMode = $today['mode'];
                    $primaryBalance = $personal['balances']->first();
                @endphp

                <section class="space-y-5">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <p class="text-sm text-gray-500">Selamat datang,</p>
                            <h2 class="mt-0.5 text-xl font-semibold text-gray-950">{{ $personal['employee']->full_name }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ $personal['employee']->jobPosition?->name ?? 'Jabatan belum diatur' }} <span class="mx-1 text-gray-300">&middot;</span> {{ $personal['employee']->department?->name ?? 'Divisi belum diatur' }}</p>
                        </div>
                        <p class="text-sm font-medium text-gray-500">{{ now()->translatedFormat('l, d F Y') }}</p>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="grid lg:grid-cols-[1.35fr_1fr]">
                            <div class="bg-gray-950 p-5 text-white sm:p-6">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Hari ini</p>
                                        <h3 class="mt-2 text-xl font-semibold">{{ $today['holiday']?->name ?? $todayMode->label }}</h3>
                                    </div>
                                    <span class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold ring-1 ring-inset ring-white/15">
                                        @if ($today['holiday']) Libur @elseif ($todayMode->isRemote()) Kerja remote @elseif ($todayMode->isWorking) Hari kerja @else Tidak bekerja @endif
                                    </span>
                                </div>
                                <div class="mt-6 grid grid-cols-2 gap-4">
                                    <div><p class="text-xs text-gray-400">Shift</p><p class="mt-1 text-sm font-semibold">{{ $todaySchedule?->shift?->name ?? '—' }}</p></div>
                                    <div><p class="text-xs text-gray-400">Jam kerja</p><p class="mt-1 text-sm font-semibold">{{ $todaySchedule?->shift?->time_range_label ?? '—' }}</p></div>
                                </div>
                            </div>

                            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Status absensi</p>
                                        @if ($todayAttendance)<x-status-badge :tone="$todayAttendance->status->tone()">{{ $todayAttendance->status->label() }}</x-status-badge>@endif
                                    </div>
                                    @if ($todayAttendance?->clock_out)
                                        <p class="mt-3 text-lg font-semibold text-gray-950">Selesai bekerja</p>
                                        <p class="mt-1 text-sm text-gray-500">Masuk {{ $todayAttendance->clock_in_label }} · Pulang {{ $todayAttendance->clock_out_label }}</p>
                                    @elseif ($todayAttendance?->clock_in)
                                        <p class="mt-3 text-lg font-semibold text-gray-950">Sudah absen masuk</p>
                                        <p class="mt-1 text-sm text-gray-500">Tercatat pukul {{ $todayAttendance->clock_in_label }}</p>
                                    @elseif ($today['holiday'] || ! $todayMode->isWorking)
                                        <p class="mt-3 text-lg font-semibold text-gray-950">Tidak ada absensi</p>
                                        <p class="mt-1 text-sm text-gray-500">Hari ini tidak dijadwalkan bekerja.</p>
                                    @else
                                        <p class="mt-3 text-lg font-semibold text-gray-950">Belum ada absensi</p>
                                        <p class="mt-1 text-sm text-gray-500">Jam masuk belum tercatat untuk hari ini.</p>
                                    @endif
                                </div>
                                @can('my-attendance.view')
                                    <a href="{{ route('my-attendance.index') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover">Lihat Absensi Saya</a>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <x-stat-card label="Jadwal Berikutnya" :value="$personal['nextWorkday'] ? $personal['nextWorkday']['date']->translatedFormat('D, d M') : 'Belum ada'" tone="violet"><x-icon name="calendar-clock" class="size-5"/></x-stat-card>
                        <x-stat-card :label="$primaryBalance ? 'Sisa '.$primaryBalance['name'] : 'Saldo Cuti'" :value="$primaryBalance ? $primaryBalance['remaining'].' hari' : '—'" :tone="$primaryBalance && $primaryBalance['remaining'] <= 0 ? 'rose' : 'emerald'"><x-icon name="calendar-clock" class="size-5"/></x-stat-card>
                        <x-stat-card label="Pengajuan Berjalan" :value="$personal['pendingTotal']" tone="sky"><x-icon name="clock" class="size-5"/></x-stat-card>
                        @if ($personal['approvalTotal'] > 0)
                            <x-stat-card label="Perlu Respons Anda" :value="$personal['approvalTotal']" tone="amber" active><x-icon name="user-check" class="size-5"/></x-stat-card>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_340px]">
                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h3 class="text-sm font-semibold text-gray-950">Yang Perlu Anda Perhatikan</h3>
                                <p class="mt-1 text-xs text-gray-500">Pengajuan aktif dan permintaan yang menunggu respons Anda.</p>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach ($personal['approvals'] as $item)
                                    <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-4 px-5 py-3.5 transition hover:bg-amber-50/50">
                                        <span class="text-sm font-medium text-gray-800">{{ $item['label'] }}</span><span class="rounded-md bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-800">{{ $item['count'] }}</span>
                                    </a>
                                @endforeach
                                @foreach ($personal['requests'] as $item)
                                    <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-4 px-5 py-3.5 transition hover:bg-gray-50">
                                        <span class="text-sm text-gray-700">{{ $item['label'] }} sedang diproses</span><span class="rounded-md bg-sky-50 px-2.5 py-1 text-sm font-semibold text-sky-800">{{ $item['count'] }}</span>
                                    </a>
                                @endforeach
                                @if ($personal['approvals']->isEmpty() && $personal['requests']->isEmpty())
                                    <div class="px-5 py-8 text-center"><p class="text-sm font-medium text-gray-700">Semua sudah beres</p><p class="mt-1 text-xs text-gray-500">Tidak ada pengajuan atau respons yang sedang menunggu.</p></div>
                                @endif
                            </div>
                        </section>

                        <aside class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-950">Aksi Cepat</h3>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                @can('my-leave.view')<a href="{{ route('my-leave.create') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Ajukan Cuti</a>@endcan
                                @can('my-overtime.view')<a href="{{ route('my-overtime.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Ajukan Lembur</a>@endcan
                                @can('my-attendance.view')<a href="{{ route('my-attendance.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Koreksi Absensi</a>@endcan
                                @can('my-schedule.view')<a href="{{ route('my-schedule.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Tukar Jadwal</a>@endcan
                            </div>
                        </aside>
                    </div>

                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-3">
                            <div><h3 class="text-sm font-semibold text-gray-950">Jadwal 7 Hari ke Depan</h3><p class="mt-0.5 text-xs text-gray-500">Termasuk jam kantor, cuti, WFH, dan hari libur.</p></div>
                            <a href="{{ route('my-roster.index') }}" class="shrink-0 text-xs font-medium text-primary hover:underline">Lihat jadwal</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead><tr><th>Tanggal</th><th>Mode</th><th>Shift</th><th>Jam</th><th>Status</th></tr></thead>
                                <tbody>
                                    @foreach ($personal['schedule'] as $row)
                                        <tr @class(['bg-primary/[0.03]' => $row['date']->isToday()])>
                                            <td class="text-sm text-gray-700"><span @class(['font-semibold text-primary' => $row['date']->isToday()])>{{ $row['date']->translatedFormat('D, d M Y') }}</span>@if ($row['date']->isToday())<span class="ml-1 text-xs text-primary">Hari ini</span>@endif</td>
                                            <td class="text-sm"><span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $row['holiday'] ? 'bg-gray-100 text-gray-600' : $row['mode']->chipClasses() }}">{{ $row['holiday']?->name ?? $row['mode']->label }}</span></td>
                                            <td class="text-sm text-gray-700">{{ $row['schedule']?->shift?->name ?? '—' }}</td>
                                            <td class="text-sm text-gray-500">{{ $row['schedule']?->shift?->time_range_label ?? '—' }}</td>
                                            <td class="text-sm text-gray-500">{{ $row['pending'] ? 'Pengajuan '.$row['pending']->status->label() : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>
            @else
            <section class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Selamat datang,</p>
                    <h2 class="mt-0.5 text-lg font-semibold text-gray-950">{{ $personal['employee']->full_name }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ $personal['employee']->department?->name ?? '—' }} · {{ $personal['employee']->jobPosition?->name ?? '—' }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="{{ route('my-leave.index') }}" class="transition hover:opacity-90">
                        <x-stat-card label="Pengajuan Saya Berjalan" :value="$personal['myPending']" tone="sky"><x-icon name="clock" class="size-5"/></x-stat-card>
                    </a>
                    @if ($personal['needApproval'] > 0)
                        <a href="{{ route('my-leave.index') }}" class="transition hover:opacity-90">
                            <x-stat-card label="Perlu Persetujuan Anda" :value="$personal['needApproval']" tone="amber"><x-icon name="user-check" class="size-5"/></x-stat-card>
                        </a>
                    @endif
                    @foreach ($personal['balances'] as $balance)
                        <x-stat-card label="Sisa {{ $balance['name'] }}" :value="$balance['remaining'].' hari'" :tone="$balance['remaining'] <= 0 ? 'rose' : 'emerald'">
                            <x-icon name="calendar-clock" class="size-5"/>
                        </x-stat-card>
                    @endforeach
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-950">Jadwal 7 Hari ke Depan</h3>
                        @can('my-schedule.view')
                            <a href="{{ route('my-schedule.index') }}" class="text-xs font-medium text-primary hover:underline">Lihat jadwal</a>
                        @endcan
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Tanggal</th><th>Shift</th><th>Jam</th></tr></thead>
                            <tbody>
                                @forelse ($personal['schedule'] as $row)
                                    <tr>
                                        <td class="text-sm text-gray-700">{{ $row->work_date->translatedFormat('D, d M Y') }}</td>
                                        <td class="text-sm">@if ($row->is_day_off || ! $row->shift)<span class="text-gray-400">Libur</span>@else<span class="font-medium text-gray-900">{{ $row->shift->code }}</span> <span class="text-gray-500">{{ $row->shift->name }}</span>@endif</td>
                                        <td class="text-sm text-gray-500">{{ $row->shift && ! $row->is_day_off ? $row->shift->time_range_label : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="cell-empty">Belum ada jadwal 7 hari ke depan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            @endif
        @endif

        @if (! $personal && ! auth()->user()->canAny(['employees.view', 'leave.update', 'corrections.update', 'swaps.update', 'attendance-daily.view']))
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-center">
                <h2 class="text-base font-semibold text-amber-950">Akun belum tertaut ke data karyawan</h2>
                <p class="mt-2 text-sm text-amber-800">Hubungi HR atau administrator agar akun Anda dapat menampilkan jadwal, absensi, cuti, dan layanan mandiri.</p>
            </section>
        @endif

        {{-- Angka HR — sudah mengikuti cakupan lokasi/divisi pengguna --}}
        @if ($metrics->isNotEmpty())
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($metrics as $metric)
                    <x-stat-card :label="$metric['label']" :value="number_format($metric['value'])" :tone="$metric['tone']"
                        :href="route($metric['route'])" hint="Lihat data">
                        <x-icon :name="$metric['icon']" class="size-5"/>
                    </x-stat-card>
                @endforeach
            </section>
        @endif

        @canany(['employees.view', 'leave.update', 'corrections.update', 'swaps.update', 'attendance-daily.view'])
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_360px]">
                {{-- Antrean kerja: hanya yang benar-benar menunggu keputusan pengguna ini --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-950">Perlu Tindakan</h2>
                        <p class="mt-1 text-sm text-gray-500">Antrean pada lokasi kerja &amp; divisi yang menjadi cakupan Anda.</p>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse ($todo as $item)
                            <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-gray-50">
                                <p class="text-sm font-medium text-gray-800">{{ $item['label'] }}</p>
                                <span @class([
                                    'shrink-0 rounded-md px-2.5 py-1 text-sm font-semibold',
                                    'bg-amber-50 text-amber-800' => $item['tone'] === 'amber',
                                    'bg-sky-50 text-sky-800' => $item['tone'] === 'sky',
                                ])>{{ number_format($item['count']) }}</span>
                            </a>
                        @empty
                            <p class="px-5 py-8 text-center text-sm text-gray-500">Tidak ada yang menunggu keputusan Anda. 🎉</p>
                        @endforelse
                    </div>
                </div>

                <aside>
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-gray-950">Aksi Cepat</h2>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            @can('employees.create')
                                <a href="{{ route('employees.create') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Tambah Karyawan</a>
                            @endcan
                            @canany(['reports.attendance.view', 'reports.log.view', 'reports.leave.view'])
                                <a href="{{ route('reports.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Laporan</a>
                            @endcanany
                            @can('attendance-daily.view')
                                <a href="{{ route('attendance.daily.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Absensi Harian</a>
                            @endcan
                            @can('schedules.view')
                                <a href="{{ route('attendance.schedules.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Jadwal Kerja</a>
                            @endcan
                            @can('leave.view')
                                <a href="{{ route('attendance.leave.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cuti &amp; Izin</a>
                            @endcan
                            @can('branches.view')
                                <a href="{{ route('organization.branches.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Lokasi Kerja</a>
                            @endcan
                        </div>
                    </section>
                </aside>
            </section>
        @endcanany
    </div>
</x-layouts.app>
