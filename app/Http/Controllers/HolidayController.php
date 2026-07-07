<?php

namespace App\Http\Controllers;

use App\Http\Requests\HolidayRequest;
use App\Models\Branch;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);
        $year = (int) $request->input('year', now()->year);

        $holidays = Holiday::query()
            ->with('branch')
            ->whereYear('date', $year)
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('date')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.holidays.index', [
            'holidays' => $holidays,
            'filters' => $request->only('search'),
            'perPage' => $perPage,
            'year' => $year,
            'years' => range(now()->year - 1, now()->year + 2),
        ]);
    }

    public function create(): View
    {
        return view('attendance.holidays.create', [
            'holiday' => new Holiday(['is_national' => true, 'date' => now()->toDateString()]),
            'branches' => $this->branches(),
        ]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        Holiday::query()->create($request->validated());

        return redirect()->route('attendance.holidays.index')->with('status', 'Hari libur berhasil ditambahkan.');
    }

    public function edit(Holiday $holiday): View
    {
        return view('attendance.holidays.edit', [
            'holiday' => $holiday,
            'branches' => $this->branches(),
        ]);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->validated());

        return redirect()->route('attendance.holidays.index')->with('status', 'Hari libur berhasil diperbarui.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('attendance.holidays.index')->with('status', 'Hari libur berhasil dihapus.');
    }

    private function branches()
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }
}
