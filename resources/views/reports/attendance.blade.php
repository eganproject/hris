<x-layouts.app title="Rekap Kehadiran - {{ config('app.name', 'HRIS') }}" heading="Rekap Kehadiran">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500"><a href="{{ route('reports.index') }}" class="hover:text-gray-700">Laporan</a> · {{ $month->translatedFormat('F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Rekap Kehadiran</h1>
                <p class="mt-1 text-sm text-gray-500">Ringkasan kehadiran per karyawan untuk periode terpilih.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.attendance.pdf', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <x-icon name="download" class="size-4"/> PDF
                </a>
                <a href="{{ route('reports.attendance.export', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <x-icon name="download" class="size-4"/> Excel
                </a>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('reports.attendance') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.attendance', array_merge(request()->query(), ['month' => $prevMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">‹</a>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <a href="{{ route('reports.attendance', array_merge(request()->query(), ['month' => $nextMonth])) }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">›</a>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
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
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center gap-1.5 border-b border-gray-100 px-4 py-2.5 text-xs text-gray-500">
                <x-icon name="info" class="size-3.5 shrink-0 text-gray-400"/>
                <span>Arahkan kursor ke judul kolom (bergaris putus-putus) untuk melihat keterangannya.</span>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari kerja terjadwal pada periode (tidak termasuk libur nasional & libur jadwal).">Hari</span></th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari masuk kerja — sudah termasuk telat, pulang cepat, WFH, dan dinas luar.">Hadir</span></th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari terlambat (bagian dari Hadir).">Telat</span></th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari pulang lebih cepat dari jadwal (bagian dari Hadir).">Plg Cepat</span></th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari tanpa keterangan / mangkir (tidak hadir tanpa cuti/izin yang disetujui).">Alfa</span></th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari cuti/izin yang telah disetujui.">Cuti</span></th>
                            <th class="text-center"><span class="cursor-help border-b border-dotted border-gray-400" title="Jumlah hari sakit.">Sakit</span></th>
                            <th class="text-right"><span class="cursor-help border-b border-dotted border-gray-400" title="Akumulasi menit keterlambatan selama periode.">Total Telat</span></th>
                            <th class="text-right"><span class="cursor-help border-b border-dotted border-gray-400" title="Total jam kerja yang tercatat dari absensi.">Jam Kerja</span></th>
                            <th class="text-right"><span class="cursor-help border-b border-dotted border-gray-400" title="Total lembur yang diajukan karyawan & disetujui atasan (angka resmi penggajian), bukan hitungan otomatis dari absensi.">Lembur Disetujui</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $e = $row['employee']; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('reports.attendance.detail', ['employee' => $e->id, 'month' => $month->format('Y-m')]) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $e->full_name }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $e->employee_number }} · {{ $e->departments->pluck('name')->implode(', ') ?: ($e->department?->name ?? '—') }}</p>
                                </td>
                                <td class="text-center text-sm text-gray-700">{{ $row['total_hari'] }}</td>
                                <td class="text-center text-sm font-medium text-gray-900">{{ $row['hadir'] }}</td>
                                <td class="text-center text-sm {{ $row['terlambat'] > 0 ? 'font-medium text-amber-600' : 'text-gray-400' }}">{{ $row['terlambat'] }}</td>
                                <td class="text-center text-sm {{ $row['pulang_cepat'] > 0 ? 'font-medium text-amber-600' : 'text-gray-400' }}">{{ $row['pulang_cepat'] }}</td>
                                <td class="text-center text-sm {{ $row['alfa'] > 0 ? 'font-medium text-red-600' : 'text-gray-400' }}">{{ $row['alfa'] }}</td>
                                <td class="text-center text-sm text-gray-700">{{ $row['cuti'] }}</td>
                                <td class="text-center text-sm text-gray-700">{{ $row['sakit'] }}</td>
                                <td class="text-right text-sm text-gray-700">{{ $row['terlambat_menit'] }} m</td>
                                <td class="text-right text-sm text-gray-700">{{ intdiv($row['kerja_menit'], 60) }}j {{ $row['kerja_menit'] % 60 }}m</td>
                                <td class="text-right text-sm font-medium text-gray-800">{{ intdiv($row['lembur_menit'], 60) }}j {{ $row['lembur_menit'] % 60 }}m</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="cell-empty">Belum ada data kehadiran pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        @php $kerjaTotal = (int) $rows->sum('kerja_menit'); $lemburTotal = (int) $rows->sum('lembur_menit'); @endphp
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold text-gray-900">
                                <td class="text-sm">Total · {{ $rows->count() }} karyawan</td>
                                <td class="text-center text-sm">{{ $rows->sum('total_hari') }}</td>
                                <td class="text-center text-sm">{{ $rows->sum('hadir') }}</td>
                                <td class="text-center text-sm text-amber-700">{{ $rows->sum('terlambat') }}</td>
                                <td class="text-center text-sm text-amber-700">{{ $rows->sum('pulang_cepat') }}</td>
                                <td class="text-center text-sm text-red-700">{{ $rows->sum('alfa') }}</td>
                                <td class="text-center text-sm">{{ $rows->sum('cuti') }}</td>
                                <td class="text-center text-sm">{{ $rows->sum('sakit') }}</td>
                                <td class="text-right text-sm">{{ $rows->sum('terlambat_menit') }} m</td>
                                <td class="text-right text-sm">{{ intdiv($kerjaTotal, 60) }}j {{ $kerjaTotal % 60 }}m</td>
                                <td class="text-right text-sm">{{ intdiv($lemburTotal, 60) }}j {{ $lemburTotal % 60 }}m</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <div class="space-y-1 text-xs text-gray-400">
            <p>"Hari" = hari kerja terjadwal pada periode (tidak termasuk libur nasional & libur jadwal). "Hadir" sudah termasuk telat, pulang cepat, WFH, dan dinas luar; "Cuti" termasuk izin. Karyawan aktif yang belum punya data absensi tetap tampil bernilai nol.</p>
            <p>"Lembur Disetujui" = total lembur yang telah diajukan karyawan & disetujui atasan (angka resmi untuk penggajian), bukan hitungan otomatis dari absensi.</p>
        </div>
    </div>
</x-layouts.app>
