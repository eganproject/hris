<?php

namespace App\Http\Controllers;

use App\Enums\SchedulePatternType;
use App\Http\Requests\SchedulePatternRequest;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use App\Services\DefaultOfficeSchedule;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchedulePatternController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);
        $archived = $request->input('status') === 'archived';
        $search = $request->string('search')->toString();

        // Penyaring yang sama dipakai daftar dan penghitung kedua tab, supaya angka
        // pada tab yang tidak aktif menghitung himpunan yang sama dengan yang tampil.
        $filtered = fn (): Builder => SchedulePattern::query()
            ->visibleTo($request->user())
            ->when($search, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
            ));

        $patterns = $filtered()
            ->withCount(['days', 'officeEmployees'])
            // Jumlah KARYAWAN, bukan jumlah penugasan: satu orang bisa punya beberapa
            // penugasan pada pola yang sama (perpanjangan, ganti periode), dan
            // menghitungnya berkali-kali membuat kolom "Dipakai" mengada-ada.
            ->addSelect(['assigned_employees_count' => ScheduleAssignment::query()
                ->selectRaw('count(distinct employee_id)')
                ->whereColumn('schedule_pattern_id', 'schedule_patterns.id'),
            ])
            ->when($archived,
                fn (Builder $query) => $query->onlyTrashed()->orderByDesc('deleted_at'),
                fn (Builder $query) => $query->orderBy('name'),
            )
            ->with(['days.shift'])
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.schedule-patterns.index', [
            'patterns' => $patterns,
            'filters' => $request->only('search'),
            'perPage' => $perPage,
            'archived' => $archived,
            'activeCount' => $filtered()->count(),
            'archivedCount' => $filtered()->onlyTrashed()->count(),
        ]);
    }

    /**
     * Siapa saja yang benar-benar memakai pola ini.
     *
     * Ada tiga jalur berbeda menuju satu pola, dan menampilkan salah satunya saja
     * memberi gambaran keliru tentang dampak mengubah atau mengarsipkannya:
     *   1. penugasan pola (ScheduleAssignment) — pekerja shift;
     *   2. karyawan jam kantor yang menunjuk pola ini sendiri;
     *   3. karyawan jam kantor tanpa pola sendiri, bila pola ini yang dipakai sebagai
     *      default global di Pengaturan.
     */
    public function employees(Request $request, SchedulePattern $schedulePattern, DefaultOfficeSchedule $officeSchedule): View
    {
        $this->authorizeVisible($request, $schedulePattern);

        $perPage = min(max((int) $request->input('per_page', 25), 10), 100);
        $search = $request->string('search')->toString();
        $includeInactive = $request->boolean('include_inactive');
        $isGlobalDefault = $officeSchedule->defaultPatternId() === $schedulePattern->id;

        $scope = DataScope::forAttendance($request->user());

        $employees = $scope->employees()
            ->when(! $includeInactive, fn (Builder $query) => $query->active())
            ->where(fn (Builder $query) => $query
                ->whereHas('scheduleAssignments', fn (Builder $inner) => $inner->where('schedule_pattern_id', $schedulePattern->id))
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('follows_office_hours', true)
                    ->where('office_pattern_id', $schedulePattern->id))
                ->when($isGlobalDefault, fn (Builder $inner) => $inner
                    ->orWhere(fn (Builder $deeper) => $deeper
                        ->where('follows_office_hours', true)
                        ->whereNull('office_pattern_id')))
            )
            ->when($search, fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('employee_number', 'like', "%{$term}%")))
            ->with([
                'branch',
                'department',
                'jobPosition',
                // Hanya penugasan pola INI yang dimuat; penugasan pola lain milik orang
                // yang sama tidak ada urusannya dengan halaman ini.
                'scheduleAssignments' => fn ($query) => $query
                    ->where('schedule_pattern_id', $schedulePattern->id)
                    ->orderBy('start_date'),
            ])
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.schedule-patterns.employees', [
            'pattern' => $schedulePattern->loadCount('days')->load('days.shift'),
            'employees' => $employees,
            'isGlobalDefault' => $isGlobalDefault,
            'filters' => ['search' => $search, 'include_inactive' => $includeInactive],
            'perPage' => $perPage,
            'hasNoScope' => $scope->isEmpty(),
        ]);
    }

    public function create(): View
    {
        return view('attendance.schedule-patterns.create', [
            'pattern' => new SchedulePattern(['type' => SchedulePatternType::FixedWeekly, 'cycle_length' => 7, 'is_active' => true]),
            'shifts' => $this->shiftOptions(),
        ]);
    }

    public function store(SchedulePatternRequest $request): RedirectResponse
    {
        $pattern = SchedulePattern::query()->create([
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
            'cycle_length' => $request->input('type') === SchedulePatternType::Rotating->value ? $request->integer('cycle_length') : 7,
            'anchor_date' => $request->input('type') === SchedulePatternType::Rotating->value ? $request->date('anchor_date') : null,
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->id,
        ]);

        $this->syncDays($pattern, $request->input('days', []), $request->input('days_wfh', []));

        return redirect()->route('attendance.schedule-patterns.index')->with('status', 'Pola jadwal berhasil dibuat.');
    }

    public function edit(Request $request, SchedulePattern $schedulePattern): View
    {
        $this->authorizeOwner($request, $schedulePattern);
        $schedulePattern->load('days');

        return view('attendance.schedule-patterns.edit', [
            'pattern' => $schedulePattern,
            'shifts' => $this->shiftOptions($schedulePattern),
        ]);
    }

    public function update(SchedulePatternRequest $request, SchedulePattern $schedulePattern): RedirectResponse
    {
        $this->authorizeOwner($request, $schedulePattern);
        $schedulePattern->update([
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
            'cycle_length' => $request->input('type') === SchedulePatternType::Rotating->value ? $request->integer('cycle_length') : 7,
            'anchor_date' => $request->input('type') === SchedulePatternType::Rotating->value ? $request->date('anchor_date') : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->syncDays($schedulePattern->fresh(), $request->input('days', []), $request->input('days_wfh', []));

        return redirect()->route('attendance.schedule-patterns.index')->with('status', 'Pola jadwal berhasil diperbarui.');
    }

    /**
     * Mengarsipkan, bukan membuang. Dulu penghapusan di sini ikut menyeret seluruh
     * penugasan karyawan yang memakai pola ini (cascadeOnDelete) tanpa bisa dibatalkan;
     * sekarang jadwal yang berjalan tetap utuh dan polanya sekadar hilang dari daftar
     * serta dari pilihan penugasan.
     */
    public function destroy(Request $request, SchedulePattern $schedulePattern, DefaultOfficeSchedule $officeSchedule): RedirectResponse
    {
        $this->authorizeOwner($request, $schedulePattern);

        // Pola default global tidak boleh diarsipkan begitu saja: begitu hilang dari
        // daftar Pengaturan, menyimpan halaman itu sekali saja akan mengosongkan
        // defaultnya — dan seluruh karyawan jam kantor yang tidak memilih pola sendiri
        // langsung kehilangan jadwalnya tanpa satu pun peringatan.
        if ($officeSchedule->defaultPatternId() === $schedulePattern->id) {
            return back()->with('error', "Pola {$schedulePattern->name} sedang dipakai sebagai pola jam kantor default. Pilih pola default lain di menu Pengaturan sebelum mengarsipkannya.");
        }

        $schedulePattern->delete();

        return redirect()
            ->route('attendance.schedule-patterns.index')
            ->with('status', "Pola {$schedulePattern->name} dipindahkan ke arsip. Jadwal yang sudah memakainya tetap berjalan, dan polanya bisa dipulihkan lewat tab Arsip.");
    }

    public function restore(Request $request, SchedulePattern $schedulePattern): RedirectResponse
    {
        $this->authorizeOwner($request, $schedulePattern);
        $schedulePattern->restore();

        // Statusnya dikembalikan apa adanya — pola yang memang sengaja dinonaktifkan
        // sebelum diarsipkan tidak boleh diam-diam muncul lagi di pilihan penugasan.
        $note = $schedulePattern->is_active
            ? ''
            : ' Statusnya masih Nonaktif, jadi belum muncul di pilihan penugasan.';

        return redirect()
            ->route('attendance.schedule-patterns.index')
            ->with('status', "Pola {$schedulePattern->name} berhasil dipulihkan.".$note);
    }

    /** Pola hanya boleh disentuh oleh pembuatnya (atau pemegang attendance.view.all). */
    private function authorizeOwner(Request $request, SchedulePattern $pattern): void
    {
        abort_unless(
            $request->user()->can(User::SCOPE_BYPASS_ATTENDANCE) || $pattern->created_by === $request->user()->id,
            403,
        );
    }

    /**
     * Aturannya sama dengan authorizeOwner untuk saat ini, tapi maksudnya beda: ini
     * soal boleh MELIHAT, bukan boleh mengubah — dan keduanya tidak harus ikut bergerak
     * kalau salah satunya nanti diperlonggar.
     */
    private function authorizeVisible(Request $request, SchedulePattern $pattern): void
    {
        abort_unless(
            $request->user()->can(User::SCOPE_BYPASS_ATTENDANCE) || $pattern->created_by === $request->user()->id,
            403,
        );
    }

    /**
     * Rewrite the pattern's slots. Each slot index maps to a shift id (or null = off),
     * and may be flagged WFH (only meaningful on a slot that has a shift).
     *
     * @param  array<int|string, mixed>  $days
     * @param  array<int|string, mixed>  $daysWfh
     */
    private function syncDays(SchedulePattern $pattern, array $days, array $daysWfh = []): void
    {
        $pattern->days()->delete();

        for ($index = 0; $index < $pattern->slotCount(); $index++) {
            $shiftId = $days[$index] ?? null;

            $pattern->days()->create([
                'day_index' => $index,
                'shift_id' => $shiftId ?: null,
                'is_wfh' => (bool) ($shiftId && ! empty($daysWfh[$index])),
            ]);
        }
    }

    /**
     * Pilihan shift untuk formulir pola.
     *
     * Selain shift yang aktif, daftar ini WAJIB memuat shift yang sudah dipakai pola
     * yang sedang disunting — termasuk yang nonaktif atau sudah diarsipkan. Tanpa itu
     * slot yang menunjuk shift semacam itu tidak punya opsi yang cocok, sehingga
     * pilihannya jatuh ke "Libur" dan sekali simpan polanya berubah diam-diam.
     *
     * @return Collection<int, Shift>
     */
    private function shiftOptions(?SchedulePattern $pattern = null): Collection
    {
        $inUse = $pattern
            ? $pattern->days->pluck('shift_id')->filter()->unique()->values()->all()
            : [];

        return Shift::withTrashed()
            ->where(function (Builder $query) use ($inUse): void {
                $query->where(fn (Builder $inner) => $inner->where('is_active', true)->whereNull('deleted_at'));

                if ($inUse !== []) {
                    $query->orWhereIn('id', $inUse);
                }
            })
            ->orderBy('start_time')
            ->orderBy('name')
            ->get();
    }
}
