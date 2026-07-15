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
        $departmentId = $request->integer('department_id') ?: null;
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;
        $scope = DataScope::forAttendance($request->user());

        // Basis (bulan + lokasi + divisi + pencarian) tanpa filter status, agar angka
        // ringkasan di atas tetap mencerminkan seluruh lembur bulan itu.
        $base = fn () => OvertimeApproval::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $q
                ->byBranch($branchId)
                ->byDepartment($departmentId)
                ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                    ->where('full_name', 'like', "%{$s}%")
                    ->orWhere('employee_number', 'like', "%{$s}%"))))
            ->tap(fn ($query) => $scope->constrain($query));

        $requests = $base()
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->with(['employee', 'supervisor'])
            ->orderBy('work_date')
            ->get();

        return view('attendance.overtime.index', [
            'requests' => $requests,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
            'status' => $status,
            'search' => $search,
            'statuses' => OvertimeApproval::statusLabels(),
            'pendingCount' => $base()->where('status', OvertimeApproval::STATUS_PENDING)->count(),
            'approvedMinutes' => (int) $base()->where('status', OvertimeApproval::STATUS_APPROVED)->sum('approved_minutes'),
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
