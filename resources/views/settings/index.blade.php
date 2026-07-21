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
                    <label for="default_office_pattern_id" class="block text-sm font-medium text-gray-800">Pola jam kantor default</label>
                    <p class="mt-1 text-xs text-gray-500">Pola acuan untuk karyawan yang ditandai <span class="font-medium">"Ikuti jam kantor (tanpa penjadwalan)"</span> di data karyawan. Absensi mereka dihitung langsung dari pola ini tanpa perlu di-assign atau digenerate roster. Biarkan kosong untuk mematikan fitur.</p>
                    <select name="default_office_pattern_id" id="default_office_pattern_id" class="mt-2 block w-full max-w-md rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">— Tidak diatur —</option>
                        @foreach ($officePatterns as $pattern)
                            <option value="{{ $pattern->id }}" @selected($officePatternId === $pattern->id)>{{ $pattern->name }}</option>
                        @endforeach
                    </select>
                    @if ($officePatterns->isEmpty())
                        <p class="mt-2 text-xs text-amber-700">Belum ada pola jadwal aktif. Buat dulu di <a href="{{ route('attendance.schedule-patterns.index') }}" class="font-medium underline">Pola Jadwal</a>.</p>
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Pengaturan</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
