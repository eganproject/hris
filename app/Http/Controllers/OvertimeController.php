<?php

namespace App\Http\Controllers;

use App\Exports\OvertimeExport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\OvertimeApproval;
use App\Support\DataScope;
use App\Support\MonthInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OvertimeController extends Controller
{
    /**
     * HR monitoring board: every overtime request in the month with its status.
     * Approvals are made by each employee's supervisor (see MyOvertimeController),
     * so this screen is read-only — HR watches and, via the recap, pays out.
     *
     * Dipersempit ke garis atasan seperti Jadwal Kerja: tiap akun hanya melihat
     * lembur bawahannya, kecuali dikecualikan lewat Kontrol Akses.
     */
    public function index(Request $request): View
    {
        [$month, $branchId, $departmentId, $status, $search, $scope, $base] = $this->filters($request);

        $perPage = min(max((int) $request->input('per_page', 50), 25), 200);

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
            'hasNoScope' => $scope->isEmpty(),
            'hasNoTeam' => $scope->hasNoTeam(),
        ]);
    }

    /**
     * Unduh Excel isi halaman pemantauan: filter & cakupan tim sama persis dengan
     * layarnya, hanya tanpa paginasi. Berkas yang memuat lebih banyak orang daripada
     * tabelnya adalah kebocoran, bukan kemudahan.
     */
    public function export(Request $request): BinaryFileResponse
    {
        [$month, $branchId, $departmentId, $status, $search, , $base] = $this->filters($request);

        $rows = $base()
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->with(['employee.departments', 'employee.department', 'employee.branch', 'employee.jobPosition', 'supervisor'])
            ->orderBy('work_date')
            ->orderBy('start_time')
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->work_date <=> $b->work_date,
                fn ($a, $b) => strnatcasecmp((string) $a->employee?->full_name, (string) $b->employee?->full_name),
            ])
            ->values();

        $export = new OvertimeExport($rows, [
            'periode' => $month->translatedFormat('F Y'),
            'status' => $status ? (OvertimeApproval::statusLabels()[$status] ?? $status) : null,
            'lokasi' => $branchId ? Branch::find($branchId)?->name : null,
            'divisi' => $departmentId ? Department::find($departmentId)?->name : null,
            'pencarian' => $search,
            'dibuat_oleh' => $request->user()?->name,
        ]);

        return Excel::download($export, 'lembur-'.$month->format('Y-m').'.xlsx');
    }

    /**
     * Filter bersama layar & ekspor. $base = bulan + lokasi + divisi + pencarian +
     * cakupan tim, tanpa filter status, agar angka ringkasan di atas tetap
     * mencerminkan seluruh lembur bulan itu.
     *
     * @return array{0: Carbon, 1: ?int, 2: ?int, 3: ?string, 4: ?string, 5: DataScope, 6: callable(): Builder}
     */
    private function filters(Request $request): array
    {
        $month = MonthInput::resolve($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;
        $scope = DataScope::forTeam($request->user());

        $base = fn () => OvertimeApproval::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $q
                ->byBranch($branchId)
                ->byDepartment($departmentId)
                ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                    ->where('full_name', 'like', "%{$s}%")
                    ->orWhere('employee_number', 'like', "%{$s}%"))))
            ->tap(fn ($query) => $scope->constrain($query));

        return [$month, $branchId, $departmentId, $status, $search, $scope, $base];
    }

    /**
     * Monthly overtime recap: approved overtime totals per employee.
     *
     * Halaman laporan, jadi dipersempit ke garis atasan seperti rekap lainnya —
     * cakupannya sama dengan daftar pemantauan lembur (index).
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
