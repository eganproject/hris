<x-layouts.app title="Dashboard - {{ config('app.name', 'HRIS') }}" heading="Dashboard">
    @php
        // Sapaan mengikuti jam server (zona waktu aplikasi), murni penyajian.
        $greeting = match (true) {
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
        $firstName = $personal ? str($personal['employee']->full_name)->before(' ')->toString() : null;
    @endphp

    <div class="space-y-4">
        {{-- Satu kepala halaman untuk semua peran. Judul halamannya sudah ada di bilah
             atas, jadi di sini yang ditampilkan sapaan dan tanggalnya saja. --}}
        <section class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold text-gray-950">
                    {{ $personal ? $greeting.', '.$firstName : 'Dashboard' }}
                </h1>
                <p class="mt-1 truncate text-sm text-gray-500">
                    @if ($personal)
                        {{ $personal['employee']->jobPosition?->name ?? 'Jabatan belum diatur' }}
                        <span class="mx-1 text-gray-300">&middot;</span>
                        {{ $personal['employee']->department?->name ?? 'Divisi belum diatur' }}
                    @else
                        Ringkasan operasional sesuai cakupan akses Anda.
                    @endif
                </p>
            </div>
            <p class="flex-none rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm">
                {{ now()->translatedFormat('l, d F Y') }}
            </p>
        </section>

        @if ($personal)
            @if ($employeeDashboard)
                @php
                    $today = $personal['today'];
                    $todayAttendance = $today['attendance'];
                    $todaySchedule = $today['schedule'];
                    $todayMode = $today['mode'];
                    $primaryBalance = $personal['balances']->first();
                @endphp

                {{-- Kartu "hari ini": separuh gelap untuk konteks jadwal, separuh terang
                     untuk keadaan absensinya. --}}
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="grid lg:grid-cols-[1.35fr_1fr]">
                        <div class="bg-gradient-to-br from-gray-950 to-gray-800 p-5 text-white sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Hari ini</p>
                                    <p class="mt-2 text-xl font-semibold">{{ $today['holiday']?->name ?? $todayMode->label }}</p>
                                </div>
                                <span class="flex-none rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold ring-1 ring-inset ring-white/15">
                                    @if ($today['holiday']) Libur @elseif ($todayMode->isRemote()) Kerja remote @elseif ($todayMode->isWorking) Hari kerja @else Tidak bekerja @endif
                                </span>
                            </div>

                            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-white/10 pt-4">
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wider text-gray-500">Shift</dt>
                                    <dd class="mt-1 truncate text-sm font-semibold">{{ $todaySchedule?->shift?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wider text-gray-500">Jam Kerja</dt>
                                    <dd class="mt-1 truncate text-sm font-semibold">{{ $todaySchedule?->shift?->time_range_label ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="flex flex-col justify-between gap-5 p-5 sm:p-6">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Status Absensi</p>
                                    @if ($todayAttendance)<x-status-badge :tone="$todayAttendance->status->tone()">{{ $todayAttendance->status->label() }}</x-status-badge>@endif
                                </div>

                                @if ($todayAttendance?->clock_out)
                                    <p class="mt-3 text-lg font-semibold text-gray-950">Selesai bekerja</p>
                                    <p class="mt-1 text-sm text-gray-500">Masuk {{ $todayAttendance->clock_in_label }} <span class="mx-0.5 text-gray-300">&middot;</span> Pulang {{ $todayAttendance->clock_out_label }}</p>
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
                </section>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-stat-card label="Jadwal Berikutnya" :value="$personal['nextWorkday'] ? $personal['nextWorkday']['date']->translatedFormat('D, d M') : 'Belum ada'" tone="violet"><x-icon name="calendar-clock" class="size-5"/></x-stat-card>
                    <x-stat-card :label="$primaryBalance ? 'Sisa '.$primaryBalance['name'] : 'Saldo Cuti'" :value="$primaryBalance ? $primaryBalance['remaining'].' hari' : '—'" :tone="$primaryBalance && $primaryBalance['remaining'] <= 0 ? 'rose' : 'emerald'"><x-icon name="calendar-clock" class="size-5"/></x-stat-card>
                    <x-stat-card label="Pengajuan Berjalan" :value="$personal['pendingTotal']" tone="sky"><x-icon name="clock" class="size-5"/></x-stat-card>
                    @if ($personal['approvalTotal'] > 0)
                        <x-stat-card label="Perlu Respons Anda" :value="$personal['approvalTotal']" tone="amber" active><x-icon name="user-check" class="size-5"/></x-stat-card>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_340px] xl:items-start">
                    <x-dashboard.card
                        title="Yang Perlu Anda Perhatikan"
                        subtitle="Pengajuan aktif dan permintaan yang menunggu respons Anda."
                        icon="clock"
                        tone="amber"
                        flush>
                        <ul class="divide-y divide-gray-50">
                            @foreach ($personal['approvals'] as $item)
                                <li>
                                    <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-amber-50/60">
                                        <span class="min-w-0 truncate text-sm font-medium text-gray-800">{{ $item['label'] }}</span>
                                        <span class="flex-none rounded-md bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">{{ $item['count'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                            @foreach ($personal['requests'] as $item)
                                <li>
                                    <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-gray-50">
                                        <span class="min-w-0 truncate text-sm text-gray-700">{{ $item['label'] }} sedang diproses</span>
                                        <span class="flex-none rounded-md bg-sky-50 px-2.5 py-1 text-sm font-semibold text-sky-800 ring-1 ring-inset ring-sky-200">{{ $item['count'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                            @if ($personal['approvals']->isEmpty() && $personal['requests']->isEmpty())
                                <li class="px-5 py-10 text-center">
                                    <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                        <x-icon name="user-check" class="size-5"/>
                                    </span>
                                    <p class="mt-3 text-sm font-medium text-gray-700">Semua sudah beres</p>
                                    <p class="mt-1 text-xs text-gray-500">Tidak ada pengajuan atau respons yang sedang menunggu.</p>
                                </li>
                            @endif
                        </ul>
                    </x-dashboard.card>

                    <aside class="space-y-4">
                        <x-dashboard.card title="Aksi Cepat" subtitle="Pintasan layanan mandiri." icon="plus" tone="primary">
                            <div class="grid grid-cols-2 gap-2">
                                @can('my-leave.view')<x-dashboard.action :href="route('my-leave.create')" icon="calendar-clock" label="Ajukan Cuti"/>@endcan
                                @can('my-overtime.view')<x-dashboard.action :href="route('my-overtime.index')" icon="clock" label="Ajukan Lembur"/>@endcan
                                @can('my-attendance.view')<x-dashboard.action :href="route('my-attendance.index')" icon="pencil" label="Koreksi Absensi"/>@endcan
                                @can('my-schedule.view')<x-dashboard.action :href="route('my-schedule.index')" icon="refresh" label="Tukar Jadwal"/>@endcan
                            </div>
                        </x-dashboard.card>

                        @include('dashboard._birthdays')
                    </aside>
                </div>

                <x-dashboard.card
                    title="Jadwal 7 Hari ke Depan"
                    subtitle="Termasuk jam kantor, cuti, WFH, dan hari libur."
                    icon="calendar-clock"
                    tone="violet"
                    flush>
                    <x-slot:action>
                        <a href="{{ route('my-roster.index') }}" class="text-xs font-medium text-primary hover:underline">Lihat jadwal</a>
                    </x-slot:action>

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
                </x-dashboard.card>
            @else
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

                <x-dashboard.card
                    title="Jadwal 7 Hari ke Depan"
                    subtitle="Jadwal kerja Anda sepekan ke depan."
                    icon="calendar-clock"
                    tone="violet"
                    flush>
                    @can('my-schedule.view')
                        <x-slot:action>
                            <a href="{{ route('my-schedule.index') }}" class="text-xs font-medium text-primary hover:underline">Lihat jadwal</a>
                        </x-slot:action>
                    @endcan

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
                </x-dashboard.card>
            @endif
        @endif

        @if (! $personal && ! auth()->user()->canAny(['employees.view', 'leave.update', 'corrections.update', 'swaps.update', 'attendance-daily.view']))
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-center">
                <h2 class="text-base font-semibold text-amber-950">Akun belum tertaut ke data karyawan</h2>
                <p class="mt-2 text-sm text-amber-800">Hubungi HR atau administrator agar akun Anda dapat menampilkan jadwal, absensi, cuti, dan layanan mandiri.</p>
            </section>
        @endif

        {{-- Angka HR — sudah mengikuti cakupan lokasi/divisi pengguna --}}
        @if (! $employeeDashboard && $metrics->isNotEmpty())
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($metrics as $metric)
                    <x-stat-card :label="$metric['label']" :value="number_format($metric['value'])" :tone="$metric['tone']"
                        :href="route($metric['route'])" hint="Lihat data">
                        <x-icon :name="$metric['icon']" class="size-5"/>
                    </x-stat-card>
                @endforeach
            </section>
        @endif

        @if (! $employeeDashboard)
        @canany(['employees.view', 'leave.update', 'corrections.update', 'swaps.update', 'attendance-daily.view'])
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_360px] xl:items-start">
                {{-- Antrean kerja: hanya yang benar-benar menunggu keputusan pengguna ini --}}
                <x-dashboard.card
                    title="Perlu Tindakan"
                    subtitle="Antrean pada lokasi kerja & divisi yang menjadi cakupan Anda."
                    icon="alert-triangle"
                    tone="amber"
                    flush>
                    <ul class="divide-y divide-gray-50">
                        @forelse ($todo as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-4 px-5 py-3.5 transition hover:bg-gray-50">
                                    <span class="min-w-0 truncate text-sm font-medium text-gray-800">{{ $item['label'] }}</span>
                                    <span @class([
                                        'flex-none rounded-md px-2.5 py-1 text-sm font-semibold ring-1 ring-inset',
                                        'bg-amber-50 text-amber-800 ring-amber-200' => $item['tone'] === 'amber',
                                        'bg-sky-50 text-sky-800 ring-sky-200' => $item['tone'] === 'sky',
                                    ])>{{ number_format($item['count']) }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="px-5 py-10 text-center">
                                <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                    <x-icon name="user-check" class="size-5"/>
                                </span>
                                <p class="mt-3 text-sm font-medium text-gray-700">Antrean bersih</p>
                                <p class="mt-1 text-xs text-gray-500">Tidak ada yang menunggu keputusan Anda.</p>
                            </li>
                        @endforelse
                    </ul>
                </x-dashboard.card>

                <aside class="space-y-4">
                    <x-dashboard.card title="Aksi Cepat" subtitle="Pintasan menu yang paling sering dibuka." icon="plus" tone="primary">
                        <div class="grid grid-cols-2 gap-2">
                            @can('employees.create')<x-dashboard.action :href="route('employees.create')" icon="plus" label="Tambah Karyawan"/>@endcan
                            @canany(['reports.attendance.view', 'reports.log.view', 'reports.leave.view'])
                                <x-dashboard.action :href="route('reports.index')" icon="download" label="Laporan"/>
                            @endcanany
                            @can('attendance-daily.view')<x-dashboard.action :href="route('attendance.daily.index')" icon="clock" label="Absensi Harian"/>@endcan
                            @can('schedules.view')<x-dashboard.action :href="route('attendance.schedules.index')" icon="calendar-clock" label="Jadwal Kerja"/>@endcan
                            @can('leave.view')<x-dashboard.action :href="route('attendance.leave.index')" icon="briefcase" label="Cuti & Izin"/>@endcan
                            @can('branches.view')<x-dashboard.action :href="route('organization.branches.index')" icon="map-pin" label="Lokasi Kerja"/>@endcan
                        </div>
                    </x-dashboard.card>

                    @include('dashboard._birthdays')
                </aside>
            </div>
        @endcanany
        @endif
    </div>
</x-layouts.app>
