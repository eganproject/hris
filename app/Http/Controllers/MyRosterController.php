<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only self-service roster: lets any logged-in employee check their own work
 * schedule (shift / day off) for a month, plus a quick list of upcoming work days.
 * Available to every authenticated user with a linked employee record — no special
 * permission required (unlike the shift-swap page).
 */
class MyRosterController extends Controller
{
    public function index(Request $request): View
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        $month = $this->resolveMonth($request->input('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $schedules = $employee->schedules()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->with('shift')
            ->get()
            ->keyBy(fn ($row) => $row->work_date->toDateString());

        // National holidays (and this employee's branch holidays) overlay the grid.
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $employee->branch_id))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        $upcoming = $employee->schedules()
            ->whereDate('work_date', '>=', now()->toDateString())
            ->where('is_day_off', false)
            ->whereNotNull('shift_id')
            ->with('shift')
            ->orderBy('work_date')
            ->limit(7)
            ->get();

        $workDays = $schedules->filter(fn ($row) => ! $row->is_day_off && $row->shift_id !== null)->count();

        return view('attendance.my-roster.index', [
            'employee' => $employee,
            'month' => $month,
            'days' => collect(CarbonPeriod::create($from, $to)->toArray()),
            'schedules' => $schedules,
            'holidays' => $holidays,
            'upcoming' => $upcoming,
            'workDays' => $workDays,
            'offDays' => $schedules->count() - $workDays,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'today' => now()->startOfDay(),
        ]);
    }

    private function resolveMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
