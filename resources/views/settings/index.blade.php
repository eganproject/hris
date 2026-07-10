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

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Pengaturan</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
