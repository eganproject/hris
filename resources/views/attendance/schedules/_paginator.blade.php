{{--
    Footer paginasi yang dipakai bersama grid roster dan daftar penugasan: pemilih
    jumlah baris, rentang yang sedang tampil, lalu tautan halaman.

    Formnya membawa ulang seluruh query string kecuali per_page & page, sehingga
    bulan, filter, dan tab yang aktif tidak hilang saat ukuran halaman diubah.

    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
    @var string $unit  kata benda untuk teks rentang, mis. "karyawan"
--}}
@if ($paginator->total() > 0)
    <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('attendance.schedules.index') }}" class="flex items-center gap-2">
                @foreach (request()->except(['per_page', 'page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="schedule_per_page" class="text-xs text-gray-500">Per halaman</label>
                <select id="schedule_per_page" name="per_page" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-2 py-1.5 text-xs shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
            <span class="text-xs text-gray-500">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ number_format($paginator->total()) }} {{ $unit }}</span>
        </div>
        <div>{{ $paginator->links() }}</div>
    </div>
@endif
