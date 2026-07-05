<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShiftRequest;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $shifts = Shift::query()
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('start_time')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.shifts.index', ['shifts' => $shifts, 'filters' => $request->only('search'), 'perPage' => $perPage]);
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

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return redirect()->route('attendance.shifts.index')->with('status', 'Shift berhasil dihapus.');
    }
}
