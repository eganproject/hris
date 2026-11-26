<x-layouts.app title="Pengaturan - {{ config('app.name', 'HRIS') }}" heading="Pengaturan">
    <div class="mx-auto max-w-3xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Konfigurasi aplikasi</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Pengaturan</h1>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-950">Absensi & Jadwal</h2>

            <form method="POST" action="{{ route('settings.update') }}" data-no-confirm="true" class="mt-4">
                @csrf
                @method('PUT')

                <label class="flex items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-gray-800">Auto-generate roster</span>
                        <span class="mt-1 block text-xs text-gray-500">Perpanjang jadwal harian karyawan ke depan secara otomatis tiap malam dari pola yang di-assign. Jika dimatikan, roster hanya terisi lewat tombol "Generate Roster" manual. Override & pengisian jadwal manual tetap berfungsi.</span>
                    </span>
                    <span class="relative inline-flex flex-none">
                        <input type="checkbox" name="roster_autogenerate" value="1" @checked($rosterAutogenerate) class="peer sr-only">
                        <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-primary"></span>
                        <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </span>
                </label>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <span class="block text-sm font-medium text-gray-800">Batas sesi absen aplikasi HP</span>
                    <p class="mt-1 text-xs text-gray-500">Setelah absen masuk lewat aplikasi, absen masuk berikutnya dikunci sampai karyawan absen pulang, paling lama selama batas ini sejak jam masuk. Lewat dari batas, kuncinya hilang dan jam pulang yang terlewat diisi lewat koreksi.</p>
                    @php $sessionMax = (int) old('mobile_session_max_minutes', $mobileSessionMaxMinutes); @endphp
                    <div class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                        <input type="number" name="mobile_session_hours" min="1" max="47" value="{{ old('mobile_session_hours', intdiv($sessionMax, 60)) }}" aria-label="Jam" class="block w-20 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <span>jam</span>
                        <input type="number" name="mobile_session_minutes" min="0" max="59" value="{{ old('mobile_session_minutes', $sessionMax % 60) }}" aria-label="Menit" class="block w-20 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <span>menit</span>
                    </div>
                    @error('mobile_session_hours')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <input type="hidden" name="push_settings" value="1">
                    <span class="block text-sm font-medium text-gray-800">Notifikasi absen ke HP karyawan</span>
                    <p class="mt-1 text-xs text-gray-500">Karyawan menerima notifikasi di aplikasi HP setiap absennya tercatat, walau aplikasinya sedang ditutup. Juga tercatat di daftar notifikasi aplikasi.</p>
                    @if ($pushConfigured)
                        <p class="mt-2 text-xs font-medium text-emerald-700">Firebase tersambung. HP yang terdaftar menerima notifikasi: {{ $pushDeviceCount }}.</p>
                    @else
                        <p class="mt-2 text-xs text-amber-700">Firebase belum tersambung: notifikasi hanya masuk ke daftar notifikasi aplikasi, belum muncul di layar HP. Isi <code>FIREBASE_CREDENTIALS</code> di file .env server.</p>
                    @endif
                    <label class="mt-4 flex items-start justify-between gap-4">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Absen masuk</span>
                            <span class="mt-1 block text-xs text-gray-500">Kirim notifikasi saat absen masuk tercatat.</span>
                        </span>
                        <span class="relative inline-flex flex-none">
                            <input type="checkbox" name="push_attendance_in" value="1" @checked($pushIn) class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-primary"></span>
                            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    <label class="mt-4 flex items-start justify-between gap-4">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Absen pulang</span>
                            <span class="mt-1 block text-xs text-gray-500">Kirim notifikasi saat absen pulang tercatat.</span>
                        </span>
                        <span class="relative inline-flex flex-none">
                            <input type="checkbox" name="push_attendance_out" value="1" @checked($pushOut) class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-primary"></span>
                            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    <label class="mt-4 flex items-start justify-between gap-4">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Termasuk absen dari mesin absensi</span>
                            <span class="mt-1 block text-xs text-gray-500">Jika dimatikan, hanya absen dari tablet kantor yang dikirimi notifikasi.</span>
                        </span>
                        <span class="relative inline-flex flex-none">
                            <input type="checkbox" name="push_attendance_machine" value="1" @checked($pushMachine) class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-primary"></span>
                            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                    <p class="mt-5 text-xs text-gray-500">Kata-kata di bawah boleh diubah. Penanda berikut akan diganti otomatis:
                        @foreach (\App\Services\Push\AttendancePushNotifier::PLACEHOLDERS as $tag => $meaning)
                            <code class="rounded bg-gray-100 px-1 text-gray-700">{{ $tag }}</code> {{ $meaning }}@if (! $loop->last);@else.@endif
                        @endforeach
                        Kosongkan kolom untuk kembali ke kata-kata bawaan.</p>

                    <div class="mt-5 rounded-md border border-gray-200 p-4">
                        <span class="block text-sm font-medium text-gray-800">Kata-kata notifikasi absen masuk</span>
                        <label for="push_in_title" class="mt-3 block text-xs font-medium text-gray-600">Judul</label>
                        <input type="text" id="push_in_title" name="push_in_title" maxlength="80" value="{{ old('push_in_title', $pushTemplates['in']['title']) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @error('push_in_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <label for="push_in_body" class="mt-3 block text-xs font-medium text-gray-600">Isi</label>
                        <textarea id="push_in_body" name="push_in_body" rows="2" maxlength="250" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('push_in_body', $pushTemplates['in']['body']) }}</textarea>
                        @error('push_in_body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5 rounded-md border border-gray-200 p-4">
                        <span class="block text-sm font-medium text-gray-800">Kata-kata notifikasi absen pulang</span>
                        <label for="push_out_title" class="mt-3 block text-xs font-medium text-gray-600">Judul</label>
                        <input type="text" id="push_out_title" name="push_out_title" maxlength="80" value="{{ old('push_out_title', $pushTemplates['out']['title']) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @error('push_out_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <label for="push_out_body" class="mt-3 block text-xs font-medium text-gray-600">Isi</label>
                        <textarea id="push_out_body" name="push_out_body" rows="2" maxlength="250" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('push_out_body', $pushTemplates['out']['body']) }}</textarea>
                        @error('push_out_body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <span class="block text-sm font-medium text-gray-800">Pola jam kantor</span>
                    <p class="mt-1 text-xs text-gray-500">Centang pola yang boleh dipilih untuk karyawan bertanda <span class="font-medium">"Ikuti jam kantor (tanpa penjadwalan)"</span>. Absensi mereka dihitung langsung dari pola tersebut tanpa perlu di-assign atau digenerate roster. Kosongkan semua untuk mematikan fitur.</p>

                    @error('office_pattern_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                    @if ($officePatterns->isEmpty())
                        <p class="mt-3 text-xs text-amber-700">Belum ada pola jadwal aktif. Buat dulu di <a href="{{ route('attendance.schedule-patterns.index') }}" class="font-medium underline">Pola Jadwal</a>.</p>
                    @else
                        @php $checkedIds = collect(old('office_pattern_ids', $officePatternIds))->map(fn ($id) => (int) $id); @endphp
                        <div class="mt-3 max-w-md divide-y divide-gray-100 rounded-md border border-gray-200">
                            @foreach ($officePatterns as $pattern)
                                <label class="flex items-start gap-3 px-4 py-3 text-sm hover:bg-gray-50">
                                    <input type="checkbox" name="office_pattern_ids[]" value="{{ $pattern->id }}"
                                        @checked($checkedIds->contains($pattern->id))
                                        data-office-pattern
                                        data-label="{{ $pattern->name }}"
                                        class="mt-0.5 size-4 shrink-0 rounded border-gray-300 text-primary focus:ring-primary">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-gray-900">{{ $pattern->name }}</span>
                                        <span class="mt-0.5 block text-xs text-gray-500">
                                            {{ $pattern->type->label() }}
                                            @if ($pattern->office_employees_count > 0)
                                                <span class="text-gray-300">·</span>
                                                <span class="font-medium text-gray-600">dipakai {{ $pattern->office_employees_count }} karyawan</span>
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Mencabut centang hanya menghilangkan pola dari pilihan ke depan. Karyawan yang sudah memakainya tetap berjalan dengan pola itu sampai diubah satu per satu.</p>

                        <label for="default_office_pattern_id" class="mt-5 block text-sm font-medium text-gray-800">Pola default</label>
                        <p class="mt-1 text-xs text-gray-500">Dipakai karyawan jam kantor yang tidak memilih pola sendiri. Harus salah satu pola yang dicentang di atas.</p>
                        <select name="default_office_pattern_id" id="default_office_pattern_id" data-office-default class="mt-2 block w-full max-w-md rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">— Tidak diatur —</option>
                            @foreach ($officePatterns as $pattern)
                                <option value="{{ $pattern->id }}" @selected((int) old('default_office_pattern_id', $officePatternId) === $pattern->id)>{{ $pattern->name }}</option>
                            @endforeach
                        </select>
                        @error('default_office_pattern_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Pengaturan</button>
                </div>
            </form>
        </section>

        <section id="uji-notifikasi" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-950">Uji notifikasi ke HP</h2>
            <p class="mt-1 text-xs text-gray-500">Kirim notifikasi percobaan ke HP seorang karyawan untuk memastikan notifikasi muncul walau aplikasinya ditutup. Hasilnya menunjukkan di mana masalahnya bila tidak muncul.</p>

            @if (session('push_test'))
                @php $pt = session('push_test'); @endphp
                <div class="mt-4 rounded-md border px-4 py-3 text-sm {{ $pt['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                    @foreach ($pt['lines'] as $line)
                        <p @class(['mt-1' => ! $loop->first])>{{ $line }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('settings.push-test') }}#uji-notifikasi" data-no-confirm="true" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="push_test_employee" class="block text-xs font-medium text-gray-600">Karyawan</label>
                    <select id="push_test_employee" name="employee_id" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Pilih karyawan…</option>
                        @foreach ($pushEmployees as $pe)
                            <option value="{{ $pe->id }}" @selected((int) old('employee_id') === $pe->id)>{{ $pe->full_name }}{{ $pe->employee_number ? ' ('.$pe->employee_number.')' : '' }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 shadow-xs transition hover:bg-gray-50">Kirim notifikasi uji</button>
            </form>
        </section>
    </div>

    @push('scripts')
    <script>
        (function () {
            const boxes = Array.from(document.querySelectorAll('[data-office-pattern]'));
            const select = document.querySelector('[data-office-default]');

            if (!boxes.length || !select) return;

            // Dropdown default hanya boleh menawarkan pola yang dicentang — server
            // menolak yang lain, jadi lebih baik pilihannya tidak pernah muncul.
            const sync = () => {
                const allowed = new Set(boxes.filter((b) => b.checked).map((b) => b.value));

                Array.from(select.options).forEach((option) => {
                    if (option.value === '') return;
                    const ok = allowed.has(option.value);
                    option.hidden = !ok;
                    option.disabled = !ok;
                    // Pola yang barusan dicabut tidak boleh tetap terpilih diam-diam.
                    if (!ok && option.selected) select.value = '';
                });
            };

            boxes.forEach((box) => box.addEventListener('change', sync));
            sync();
        })();
    </script>
    @endpush
</x-layouts.app>
