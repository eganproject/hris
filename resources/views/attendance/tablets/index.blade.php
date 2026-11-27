<x-layouts.app title="Tablet Absensi Wajah - {{ config('app.name', 'HRIS') }}" heading="Tablet Absensi Wajah">
    @php $canUpdate = auth()->user()->can('face-tablets.update'); @endphp
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Absensi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Tablet Absensi Wajah</h1>
                <p class="mt-1 text-sm text-gray-500">Karyawan absen dengan menghadapkan wajah ke tablet kantor. Wajah dicocokkan di dalam tablet, absen langsung tercatat di Absensi Harian, dan foto bukti setiap absen bisa dilihat HR di Riwayat absen.</p>
            </div>
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('android-apps.index') }}#app-tablet" class="inline-flex shrink-0 items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-xs hover:bg-gray-50">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                    APK tablet
                </a>
            @endif
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @if ($pairing)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-gray-950">Kode pasangan untuk {{ $pairing['tablet'] }}</p>
                <p class="mt-2 font-mono text-2xl font-semibold tracking-wide text-gray-950">{{ substr($pairing['code'], 0, 4) }}-{{ substr($pairing['code'], 4) }}</p>
                <p class="mt-2 text-sm text-gray-600">Buka aplikasi Absensi Tablet, masukkan kode ini. Berlaku sampai pukul {{ $pairing['expires'] }} dan hanya ditampilkan sekali.</p>
            </div>
        @endif

        <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @include('attendance.tablets._tabs', ['active' => $tab])
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card label="Absen lewat tablet hari ini" :value="$stats['today']" tone="emerald">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
            </x-stat-card>
            <x-stat-card label="Pakai NIK + PIN (ditandai)" :value="$stats['pin']" tone="amber" :href="route('attendance.tablets.logs', ['tab' => 'flagged'])">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
            </x-stat-card>
            <x-stat-card label="Karyawan belum daftar wajah" :value="$stats['missing']" tone="rose" :href="route('attendance.tablets.index', ['face' => 'missing'])">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
            </x-stat-card>
        </section>

        {{-- Perangkat tablet --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-base font-semibold text-gray-950">Perangkat tablet</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Setiap tablet terikat ke satu Lokasi Kerja dan hanya mengenali karyawan lokasi itu.</p>
                </div>
                @if ($canUpdate)
                    <details class="relative">
                        <summary class="cursor-pointer list-none rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">+ Daftarkan tablet</summary>
                        <form method="POST" action="{{ route('attendance.tablets.store') }}" class="mt-3 grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-3 sm:items-end">
                            @csrf
                            <div>
                                <label for="tablet-name" class="block text-xs font-medium text-gray-600">Nama tablet</label>
                                <input id="tablet-name" name="name" required maxlength="60" placeholder="TAB-KP-01" value="{{ old('name') }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label for="tablet-branch" class="block text-xs font-medium text-gray-600">Lokasi kerja</label>
                                <select id="tablet-branch" name="branch_id" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Buat kode pasangan</button>
                        </form>
                    </details>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Nama tablet</th><th>Lokasi kerja</th><th>Status</th><th>Terakhir aktif</th><th>Antrean</th><th>PIN HR</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($tablets as $tablet)
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $tablet->name }}</p>@if ($tablet->device_model)<p class="mt-0.5 text-xs text-gray-500">{{ $tablet->device_model }}@if ($tablet->app_version) · v{{ $tablet->app_version }}@endif</p>@endif</td>
                                <td class="text-sm text-gray-700">{{ $tablet->branch?->name }}</td>
                                <td>
                                    @php $tone = $tablet->status === 'revoked' ? 'danger' : ($tablet->status === 'pending' ? 'neutral' : ($tablet->isOnline() ? 'success' : 'warning')); @endphp
                                    <x-status-badge :tone="$tone">{{ $tablet->statusLabel() }}</x-status-badge>
                                </td>
                                <td class="text-sm text-gray-700">{{ $tablet->last_seen_at?->translatedFormat('d M H:i') ?? '–' }}</td>
                                <td class="text-sm @if ($tablet->queue_count) font-semibold text-amber-700 @else text-gray-700 @endif">{{ $tablet->queue_count }}</td>
                                <td class="text-sm">@if ($tablet->hr_pin_hash)<span class="text-gray-700">Sudah diatur</span>@else<span class="font-medium text-red-700">Belum</span>@endif</td>
                                <td class="text-right">
                                    @if ($canUpdate && $tablet->status !== 'revoked')
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <details>
                                                <summary class="cursor-pointer list-none text-sm font-medium text-primary hover:underline">Atur PIN HR</summary>
                                                <form method="POST" action="{{ route('attendance.tablets.hr-pin', $tablet) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                                    @csrf @method('PUT')
                                                    <input type="password" name="pin" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="6 angka" aria-label="PIN HR baru" class="w-24 rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                                    <input type="password" name="pin_confirmation" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="Ulangi" aria-label="Ulangi PIN HR" class="w-24 rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                                    <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-semibold text-white">Simpan</button>
                                                </form>
                                            </details>
                                            <form method="POST" action="{{ route('attendance.tablets.pairing-code', $tablet) }}">
                                                @csrf
                                                <button type="submit" class="text-sm font-medium text-primary hover:underline">Kode pasangan</button>
                                            </form>
                                            <form method="POST" action="{{ route('attendance.tablets.revoke', $tablet) }}" onsubmit="return confirm('Cabut tablet {{ $tablet->name }}? Tablet ini tidak bisa dipakai lagi.')">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-sm font-medium text-red-700 hover:underline">Cabut</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="cell-empty">Belum ada tablet. Klik "Daftarkan tablet" untuk mulai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Data wajah karyawan --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="space-y-3 border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-950">Data wajah karyawan</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Wajah didaftarkan HR lewat menu HR di tablet (3 foto, dengan persetujuan karyawan). Yang disimpan hanya pola angka wajah dan 1 foto depan.</p>
                </div>
                <form method="GET" action="{{ route('attendance.tablets.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-xs font-medium text-gray-600">Cari karyawan</label>
                        <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nama / NIK" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label for="branch" class="block text-xs font-medium text-gray-600">Lokasi kerja</label>
                        <select id="branch" name="branch" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected($filters['branch'] == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="face" class="block text-xs font-medium text-gray-600">Wajah</label>
                        <select id="face" name="face" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua</option>
                            <option value="registered" @selected($filters['face'] === 'registered')>Terdaftar</option>
                            <option value="missing" @selected($filters['face'] === 'missing')>Belum daftar</option>
                        </select>
                    </div>
                    <div class="flex gap-2 lg:col-span-2 lg:justify-end">
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
                        <a href="{{ route('attendance.tablets.index') }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Karyawan</th><th>Lokasi kerja</th><th>Wajah</th><th>Didaftarkan</th><th>PIN cadangan</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php $profile = $employee->faceProfile; @endphp
                            <tr>
                                <td><p class="font-medium text-gray-950">{{ $employee->full_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $employee->employee_number }}@if ($employee->jobPosition) · {{ $employee->jobPosition->name }}@endif</p></td>
                                <td class="text-sm text-gray-700">{{ $employee->branch?->name ?? '–' }}</td>
                                <td>
                                    @if ($profile?->hasFace())
                                        <x-status-badge tone="success">Terdaftar</x-status-badge>
                                    @else
                                        <x-status-badge tone="neutral">Belum</x-status-badge>
                                    @endif
                                </td>
                                <td class="text-sm text-gray-700">
                                    @if ($profile?->hasFace())
                                        {{ $profile->enrolled_at?->translatedFormat('d M Y') }}
                                        <p class="mt-0.5 text-xs text-gray-500">@if ($profile->enrolledTablet)Tablet {{ $profile->enrolledTablet->name }}@endif @if ($profile->consented_at)· setuju UU PDP @endif</p>
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="text-sm text-gray-700">{{ $profile?->hasPin() ? 'Sudah diatur' : '–' }}</td>
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if ($profile?->photo_path)
                                            <a href="{{ route('attendance.tablets.faces.photo', $employee) }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Lihat foto</a>
                                        @endif
                                        @if ($canUpdate)
                                            <details>
                                                <summary class="cursor-pointer list-none text-sm font-medium text-primary hover:underline">Atur PIN karyawan</summary>
                                                <form method="POST" action="{{ route('attendance.tablets.faces.pin', $employee) }}" class="mt-2 flex flex-wrap items-end justify-end gap-2">
                                                    @csrf @method('PUT')
                                                    <input type="text" name="pin" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="6 angka" aria-label="PIN absen {{ $employee->full_name }}" class="w-24 rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                                    <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-semibold text-white">Simpan</button>
                                                </form>
                                                @if ($profile?->hasPin())
                                                    <form method="POST" action="{{ route('attendance.tablets.faces.pin', $employee) }}" class="mt-2">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="clear" value="1">
                                                        <button type="submit" class="text-sm font-medium text-red-700 hover:underline">Hapus PIN</button>
                                                    </form>
                                                @endif
                                            </details>
                                            @if ($profile?->hasFace())
                                                <form method="POST" action="{{ route('attendance.tablets.faces.destroy', $employee) }}" onsubmit="return confirm('Hapus data wajah {{ $employee->full_name }}? Karyawan harus didaftarkan ulang di tablet.')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-sm font-medium text-red-700 hover:underline">Hapus wajah</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Tidak ada karyawan yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $employees->links() }}</div>
        </section>

        {{-- Pengaturan --}}
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-950">Pengaturan pengenalan wajah</h2>
            <p class="mt-0.5 text-sm text-gray-500">Berlaku untuk semua tablet setelah sinkron berikutnya (paling lama 1 menit).</p>
            <form method="POST" action="{{ route('attendance.tablets.settings') }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                <div class="flex flex-col justify-between gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center">
                    <div>
                        <label for="liveness" class="text-sm font-medium text-gray-950">Cek wajah asli</label>
                        <p class="text-sm text-gray-500">Tolak foto atau layar HP yang disodorkan ke kamera. Bila dimatikan, absen hanya mencocokkan wajah.</p>
                    </div>
                    <select id="liveness" name="liveness" @disabled(! $canUpdate) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs">
                        <option value="1" @selected($settings['liveness'])>Aktif</option>
                        <option value="0" @selected(! $settings['liveness'])>Mati</option>
                    </select>
                </div>
                <div class="flex flex-col justify-between gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center">
                    <div>
                        <label for="level" class="text-sm font-medium text-gray-950">Tingkat kecocokan</label>
                        <p class="text-sm text-gray-500">Makin ketat, makin kecil risiko salah orang, tapi karyawan lebih sering diminta coba lagi.</p>
                    </div>
                    <select id="level" name="level" @disabled(! $canUpdate) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs">
                        @foreach (\App\Services\Tablet\TabletSettings::MATCH_LEVELS as $key => $level)
                            <option value="{{ $key }}" @selected($settings['level'] === $key)>{{ $level['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col justify-between gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center">
                    <div>
                        <label for="pin" class="text-sm font-medium text-gray-950">Absen pakai NIK + PIN</label>
                        <p class="text-sm text-gray-500">Cadangan bila wajah gagal dikenali. Tetap difoto dan selalu ditandai untuk HR.</p>
                    </div>
                    <select id="pin" name="pin" @disabled(! $canUpdate) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs">
                        <option value="1" @selected($settings['pin'])>Boleh</option>
                        <option value="0" @selected(! $settings['pin'])>Tidak boleh</option>
                    </select>
                </div>
                <div class="flex flex-col justify-between gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center">
                    <div>
                        <label for="idle" class="text-sm font-medium text-gray-950">Layar istirahat</label>
                        <p class="text-sm text-gray-500">Bila tidak ada wajah selama waktu ini, kamera dimatikan dan tablet menampilkan jam redup dengan kutipan motivasi. Ketuk layar untuk absen lagi.</p>
                    </div>
                    <select id="idle" name="idle" @disabled(! $canUpdate) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs">
                        @foreach (\App\Services\Tablet\TabletSettings::IDLE_OPTIONS as $minutes => $label)
                            <option value="{{ $minutes }}" @selected($settings['idle'] === $minutes)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canUpdate)
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Simpan pengaturan</button>
                    </div>
                @endif
            </form>
        </section>
    </div>
</x-layouts.app>
