<?php

namespace App\Http\Controllers;

use App\Models\OvertimeApproval;
use App\Support\DataScope;
use App\Support\MonthInput;
use Illuminate\Http\Request;
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
        $month = MonthInput::resolve($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;
        $scope = DataScope::forAttendance($request->user());

        // Basis (bulan + lokasi + divisi + pencarian) tanpa filter status, agar angka
        // ringkasan di atas tetap mencerminkan seluruh lembur bulan itu.
        $perPage = min(max((int) $request->input('per_page', 50), 25), 200);

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
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.overtime.index', [
            'requests' => $requests,
            'perPage' => $perPage,
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
     *
     * Halaman laporan, jadi dipersempit ke garis atasan seperti rekap lainnya.
     * Daftar pemantauan lembur (index) tidak ikut — itu halaman operasional yang
     * masih memakai cakupan lokasi/divisi.
     */
    public function recap(Request $request): View
    {
        $month = MonthInput::resolve($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;
        $scope = DataScope::forTeam($request->user());

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
            'hasNoScope' => $scope->isEmpty(),
            'hasNoTeam' => $scope->hasNoTeam(),
        ]);
    }
}
