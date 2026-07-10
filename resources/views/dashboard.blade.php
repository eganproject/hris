<x-layouts.app title="Dashboard - {{ config('app.name', 'HRIS') }}" heading="Dashboard">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Overview</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Dashboard</h1>
            </div>
            <div class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                System ready
            </div>
        </section>

        {{-- Ringkasan pribadi (untuk akun yang tertaut ke karyawan) --}}
        @if ($personal)
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
                        @can('schedule.swap')
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

        {{-- Metrik admin (hanya tampil bila punya izinnya) --}}
        @if ($metrics->isNotEmpty())
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($metrics as $metric)
                    <x-stat-card :label="$metric['label']" :value="number_format($metric['value'])" :tone="$metric['tone']">
                        <x-icon :name="$metric['icon']" class="size-5"/>
                    </x-stat-card>
                @endforeach
            </section>
        @endif

        {{-- Modul admin --}}
        @if ($modules->isNotEmpty())
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_360px]">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-950">Modules</h2>
                            <p class="mt-1 text-sm text-gray-500">Core HR work areas</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 divide-y divide-gray-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                        @foreach ($modules->chunk(3) as $chunk)
                            <div class="divide-y divide-gray-100">
                                @foreach ($chunk as $module)
                                    <a href="{{ $module['route'] ? route($module['route']) : '#' }}" class="block p-5 transition hover:bg-gray-50">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="font-medium text-gray-950">{{ $module['name'] }}</p>
                                                <p class="mt-1 text-sm leading-6 text-gray-500">{{ $module['description'] }}</p>
                                            </div>
                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                                {{ number_format($module['count']) }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-gray-950">Quick Actions</h2>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            @can('employees.create')
                                <a href="{{ route('employees.create') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Tambah Karyawan</a>
                            @endcan
                            @can('attendance.view')
                                <a href="{{ route('reports.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Laporan</a>
                            @endcan
                            @can('attendance.view')
                                <a href="{{ route('attendance.daily.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Absensi Harian</a>
                            @endcan
                            @can('attendance.view')
                                <a href="{{ route('attendance.schedules.index') }}" class="rounded-md border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Jadwal Kerja</a>
                            @endcan
                        </div>
                    </section>
                </aside>
            </section>
        @endif
    </div>
</x-layouts.app>
