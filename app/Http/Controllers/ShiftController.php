<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShiftRequest;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);
        $archived = $request->input('status') === 'archived';
        $search = $request->string('search')->toString();

        // Satu penyaring dipakai dua tab, supaya pencarian tetap berlaku saat pengguna
        // berpindah ke arsip — dan supaya angka pada tab yang tidak aktif menghitung
        // himpunan yang sama, bukan seluruh tabel.
        $filtered = fn (): Builder => Shift::query()
            ->when($search, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
            ));

        $shifts = $filtered()
            ->when($archived,
                // Yang terakhir diarsipkan paling atas: itu yang paling mungkin dicari
                // orang yang baru sadar salah hapus.
                fn (Builder $query) => $query->onlyTrashed()->orderByDesc('deleted_at'),
                fn (Builder $query) => $query->orderBy('start_time')->orderBy('name'),
            )
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.shifts.index', [
            'shifts' => $shifts,
            'filters' => $request->only('search'),
            'perPage' => $perPage,
            'archived' => $archived,
            'activeCount' => $filtered()->count(),
            'archivedCount' => $filtered()->onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        return view('attendance.shifts.create', ['shift' => new Shift(['is_active' => true, 'break_minutes' => 60])]);
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        Shift::query()->create($request->payload());

        return redirect()->route('attendance.shifts.index')->with('status', 'Shift berhasil dibuat.');
    }

    public function edit(Shift $shift): View
    {
        return view('attendance.shifts.edit', ['shift' => $shift]);
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->payload());

        return redirect()->route('attendance.shifts.index')->with('status', 'Shift berhasil diperbarui.');
    }

    /**
     * Mengarsipkan, bukan membuang. Absensi dan roster yang sudah menunjuk shift ini
     * tetap menampilkannya (lihat relasi withTrashed di Attendance & EmployeeSchedule);
     * yang hilang hanyalah kemunculannya di daftar dan di pilihan pola.
     */
    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return redirect()
            ->route('attendance.shifts.index')
            ->with('status', "Shift {$shift->name} dipindahkan ke arsip. Bisa dipulihkan lewat tab Arsip.");
    }

    public function restore(Shift $shift): RedirectResponse
    {
        // Kode shift unik lintas arsip, jadi memulihkan tidak pernah bisa bentrok
        // dengan shift yang sekarang aktif.
        $shift->restore();

        // Statusnya dikembalikan apa adanya, bukan dipaksa aktif — shift yang memang
        // sengaja dinonaktifkan sebelum diarsipkan tidak boleh diam-diam hidup lagi.
        $note = $shift->is_active
            ? ''
            : ' Statusnya masih Nonaktif, jadi belum bisa dipilih di pola jadwal.';

        return redirect()
            ->route('attendance.shifts.index')
            ->with('status', "Shift {$shift->name} berhasil dipulihkan.".$note);
    }
}
