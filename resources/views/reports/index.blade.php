<x-layouts.app title="Laporan - {{ config('app.name', 'HRIS') }}" heading="Laporan">
    <div class="mx-auto max-w-5xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Pusat laporan</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Laporan</h1>
            <p class="mt-1 text-sm text-gray-500">Rekap data kehadiran, lembur, dan cuti per periode. Bisa diekspor ke Excel.</p>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <a href="{{ route('reports.attendance') }}" class="group flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary hover:shadow-md">
                <span class="flex size-10 flex-none items-center justify-center rounded-md bg-primary-soft text-gray-700">
                    <x-icon name="user-check" class="size-5"/>
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Rekap Kehadiran</h2>
                    <p class="mt-1 text-xs text-gray-500">Ringkasan hadir, terlambat, pulang cepat, alfa, cuti/sakit, jam kerja, dan lembur per karyawan.</p>
                </div>
            </a>

            <a href="{{ route('reports.attendance-log') }}" class="group flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary hover:shadow-md">
                <span class="flex size-10 flex-none items-center justify-center rounded-md bg-primary-soft text-gray-700">
                    <x-icon name="clock" class="size-5"/>
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Log Absensi</h2>
                    <p class="mt-1 text-xs text-gray-500">Rincian harian per karyawan lengkap dengan jam masuk & jam keluar, terlambat, dan jam kerja.</p>
                </div>
            </a>

            <a href="{{ route('attendance.overtime.recap') }}" class="group flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary hover:shadow-md">
                <span class="flex size-10 flex-none items-center justify-center rounded-md bg-primary-soft text-gray-700">
                    <x-icon name="clock" class="size-5"/>
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Rekap Lembur</h2>
                    <p class="mt-1 text-xs text-gray-500">Total lembur disetujui per karyawan dalam sebulan, untuk dasar penggajian.</p>
                </div>
            </a>

            <a href="{{ route('reports.leave') }}" class="group flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary hover:shadow-md">
                <span class="flex size-10 flex-none items-center justify-center rounded-md bg-primary-soft text-gray-700">
                    <x-icon name="calendar-clock" class="size-5"/>
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-gray-950">Rekap Cuti</h2>
                    <p class="mt-1 text-xs text-gray-500">Jumlah hari cuti disetujui yang terpakai & sisa kuota tahunan per karyawan.</p>
                </div>
            </a>
        </section>
    </div>
</x-layouts.app>
