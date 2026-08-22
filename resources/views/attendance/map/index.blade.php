<x-layouts.app title="Peta Absensi WFH - {{ config('app.name', 'HRIS') }}" heading="Peta Absensi WFH">
    <div class="mx-auto max-w-7xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Absensi · {{ $date->translatedFormat('l, d F Y') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Peta Absensi WFH</h1>
            <p class="mt-1 text-sm text-gray-500">Lokasi karyawan WFH dan dinas luar, diambil dari koordinat saat mereka absen masuk.</p>
        </section>

        @if ($hasNoScope)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Cakupan akses Anda belum diatur, jadi belum ada data yang bisa ditampilkan. Minta admin menetapkan lokasi kerja / divisi Anda di menu <span class="font-medium">Kontrol Akses</span>.
            </div>
        @endif

        {{-- Navigasi tanggal + filter --}}
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('attendance.map') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-center">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.map', array_merge(request()->query(), ['date' => $prevDate])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Hari sebelumnya">&lsaquo;</a>
                    <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('attendance.map', array_merge(request()->query(), ['date' => $nextDate])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" aria-label="Hari berikutnya">&rsaquo;</a>
                </div>
                <select name="branch_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Semua lokasi</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                <select name="department_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Semua divisi</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                <input name="search" value="{{ $search }}" placeholder="Cari nama / NIK" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </form>
        </section>

        {{-- Ringkasan + keterangan warna --}}
        <section class="flex flex-wrap items-center gap-3">
            <x-status-badge tone="success">Sudah absen: {{ $points->count() }}</x-status-badge>
            <x-status-badge tone="warning">Belum absen: {{ $pending->count() }}</x-status-badge>
            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500"><span class="inline-block size-3 rounded-full bg-emerald-600"></span> WFH</span>
            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500"><span class="inline-block size-3 rounded-full bg-blue-600"></span> Dinas Luar</span>
            <span class="text-xs text-gray-400">Lingkaran pucat = akurasi GPS yang dilaporkan perangkat.</span>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Peta --}}
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm lg:col-span-2">
                @if ($points->isEmpty())
                    <div class="flex h-[560px] items-center justify-center p-8 text-center">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Belum ada yang absen mandiri pada tanggal ini.</p>
                            <p class="mt-1 text-sm text-gray-500">Titik akan muncul setelah karyawan WFH atau dinas luar melakukan absen masuk beserta selfie dan lokasinya.</p>
                        </div>
                    </div>
                @else
                    <div id="attendance-map" class="h-[560px] w-full" data-points="{{ json_encode($points) }}"></div>
                @endif
            </section>

            {{-- Daftar di samping peta --}}
            <section class="space-y-6">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3"><h2 class="text-sm font-semibold text-gray-950">Sudah Absen ({{ $points->count() }})</h2></div>
                    <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                        @forelse ($points as $index => $point)
                            <li data-focus-point="{{ $index }}" class="flex cursor-pointer items-center gap-3 px-4 py-3 transition hover:bg-gray-50" title="Klik untuk menyorot di peta">
                                @if ($point['photo_url'])
                                    <img src="{{ $point['photo_url'] }}" alt="Selfie {{ $point['name'] }}" class="size-9 flex-none rounded object-cover ring-1 ring-gray-200">
                                @else
                                    <span class="size-9 flex-none rounded bg-gray-100"></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $point['name'] }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $point['status_label'] }} · masuk {{ $point['clock_in'] }}</p>
                                </div>
                                <span class="size-2.5 flex-none rounded-full {{ $point['status'] === 'wfh' ? 'bg-emerald-600' : 'bg-blue-600' }}"></span>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-gray-400">Belum ada.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-gray-950">Belum Absen ({{ $pending->count() }})</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Dijadwalkan WFH / dinas luar tapi belum ada koordinat.</p>
                    </div>
                    <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
                        @forelse ($pending as $row)
                            <li class="px-4 py-3">
                                <a href="{{ route('attendance.daily.history', $row['employee']) }}" class="text-sm font-medium text-gray-900 hover:text-primary">{{ $row['employee']->full_name }}</a>
                                <p class="text-xs text-gray-500">
                                    {{ $row['employee']->employee_number ?? '—' }}
                                    @if ($row['attendance']?->clock_in)
                                        · absen {{ $row['attendance']->clock_in_label }} tanpa koordinat
                                    @endif
                                </p>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-gray-400">Semua sudah absen.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/attendance-map.js')
    @endpush
</x-layouts.app>
