<?php

namespace App\Http\Controllers;

use App\Models\OvertimeApproval;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class OvertimeController extends Controller
{
    /**
     * HR monitoring board: every overtime request in the month with its status.
     * Approvals are made by each employee's supervisor (see MyOvertimeController),
     * so this screen is read-only — HR watches and, via the recap, pays out.
     */
    public function index(Request $request): View
    {
        $month = $this->resolveMonth($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;
        $scope = DataScope::forAttendance($request->user());

        $requests = OvertimeApproval::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $q->when($branchId, fn ($q) => $q->where('branch_id', $branchId)))
            ->tap(fn ($query) => $scope->constrain($query))
            ->with(['employee', 'supervisor'])
            ->orderBy('work_date')
            ->get();

        return view('attendance.overtime.index', [
            'requests' => $requests,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => $scope->branches(),
            'branchId' => $branchId,
            'pendingCount' => $requests->where('status', OvertimeApproval::STATUS_PENDING)->count(),
            'approvedMinutes' => (int) $requests->where('status', OvertimeApproval::STATUS_APPROVED)->sum('approved_minutes'),
        ]);
    }

    /**
     * Monthly overtime recap: approved overtime totals per employee.
     */
    public function recap(Request $request): View
    {
        $month = $this->resolveMonth($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;
        $scope = DataScope::forAttendance($request->user());

        $rows = OvertimeApproval::query()
            ->approved()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $q->when($branchId, fn ($q) => $q->where('branch_id', $branchId)))
            ->tap(fn ($query) => $scope->constrain($query))
            ->selectRaw('employee_id, count(*) as days, sum(approved_minutes) as minutes')
            ->groupBy('employee_id')
            ->get();

        $employees = $scope->employees()->whereIn('id', $rows->pluck('employee_id'))->orderBy('full_name')->get()->keyBy('id');

        return view('attendance.overtime.recap', [
            'rows' => $rows->sortByDesc('minutes')->values(),
            'employees' => $employees,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => $scope->branches(),
            'branchId' => $branchId,
            'totalMinutes' => (int) $rows->sum('minutes'),
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
