<?php

namespace App\Http\Controllers;

use App\Enums\SchedulePatternType;
use App\Http\Requests\SchedulePatternRequest;
use App\Models\SchedulePattern;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchedulePatternController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $patterns = SchedulePattern::query()
            ->visibleTo($request->user())
            ->withCount(['assignments', 'days'])
            ->with(['days.shift'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.schedule-patterns.index', [
            'patterns' => $patterns,
            'filters' => $request->only('search'),
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('attendance.schedule-patterns.create', [
            'pattern' => new SchedulePattern(['type' => SchedulePatternType::FixedWeekly, 'cycle_length' => 7, 'is_active' => true]),
            'shifts' => $this->activeShifts(),
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
            'shifts' => $this->activeShifts(),
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

    public function destroy(Request $request, SchedulePattern $schedulePattern): RedirectResponse
    {
        $this->authorizeOwner($request, $schedulePattern);
        $schedulePattern->delete();

        return redirect()->route('attendance.schedule-patterns.index')->with('status', 'Pola jadwal berhasil dihapus.');
    }

    /** Pola hanya boleh disentuh oleh pembuatnya (atau pemegang attendance.view.all). */
    private function authorizeOwner(Request $request, SchedulePattern $pattern): void
    {
        abort_unless(
            $request->user()->can(\App\Models\User::SCOPE_BYPASS_ATTENDANCE) || $pattern->created_by === $request->user()->id,
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

    private function activeShifts()
    {
        return Shift::query()->where('is_active', true)->orderBy('start_time')->orderBy('name')->get();
    }
}
