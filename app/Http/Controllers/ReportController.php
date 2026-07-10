<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Exports\AttendanceReportExport;
use App\Exports\LeaveReportExport;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\AttendanceReport;
use App\Support\LeaveReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReport $attendanceReport,
        private readonly LeaveReport $leaveReport,
    ) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function attendance(Request $request): View
    {
        [$month, $from, $to, $branchId, $departmentId] = $this->filters($request);

        return view('reports.attendance', [
            'rows' => $this->attendanceReport->rows($from, $to, $branchId, $departmentId),
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
        ]);
    }

    public function attendanceExport(Request $request): BinaryFileResponse
    {
        [$month, $from, $to, $branchId, $departmentId] = $this->filters($request);

        $rows = $this->attendanceReport->rows($from, $to, $branchId, $departmentId);

        return Excel::download(
            new AttendanceReportExport($rows),
            'rekap-kehadiran-'.$month->format('Y-m').'.xlsx',
        );
    }

    public function attendancePdf(Request $request): Response
    {
        [$month, $from, $to, $branchId, $departmentId] = $this->filters($request);

        $pdf = Pdf::loadView('reports.pdf.attendance', [
            'rows' => $this->attendanceReport->rows($from, $to, $branchId, $departmentId),
            'month' => $month,
            'branchName' => $branchId ? Branch::find($branchId)?->name : null,
            'departmentName' => $departmentId ? Department::find($departmentId)?->name : null,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rekap-kehadiran-'.$month->format('Y-m').'.pdf');
    }

    /**
     * Per-employee daily attendance breakdown for the selected month.
     */
    public function employeeAttendance(Request $request, Employee $employee): View
    {
        $month = $this->resolveMonth($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->with('shift')
            ->orderBy('work_date')
            ->get();

        // Approved overtime per date (authoritative figure), keyed by Y-m-d.
        $approvedOvertime = DB::table('overtime_approvals')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from, $to])
            ->pluck('approved_minutes', 'work_date');

        return view('reports.attendance-detail', [
            'employee' => $employee->load(['branch', 'department', 'jobPosition']),
            'records' => $records,
            'approvedOvertime' => $approvedOvertime,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'summary' => [
                'total_hari' => $records->count(),
                'hadir' => $records->filter(fn ($r) => in_array($r->status?->value, ['present', 'late', 'early_leave', 'wfh', 'business_trip'], true))->count(),
                'terlambat' => $records->filter(fn ($r) => $r->status?->value === 'late')->count(),
                'alfa' => $records->filter(fn ($r) => $r->status?->value === 'absent')->count(),
                'terlambat_menit' => (int) $records->sum('late_minutes'),
                'kerja_menit' => (int) $records->sum('work_minutes'),
                'lembur_menit' => (int) $approvedOvertime->sum(),
            ],
        ]);
    }

    public function leave(Request $request): View
    {
        $year = $this->resolveYear($request->input('year'));
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;

        $report = $this->leaveReport->build($year, $branchId, $departmentId);

        return view('reports.leave', [
            'types' => $report['types'],
            'rows' => $report['rows'],
            'year' => $year,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
        ]);
    }

    public function leaveExport(Request $request): BinaryFileResponse
    {
        $year = $this->resolveYear($request->input('year'));
        $report = $this->leaveReport->build($year, $request->integer('branch_id') ?: null, $request->integer('department_id') ?: null);

        return Excel::download(
            new LeaveReportExport($report['rows'], $report['types']),
            'rekap-cuti-'.$year.'.xlsx',
        );
    }

    public function leavePdf(Request $request): Response
    {
        $year = $this->resolveYear($request->input('year'));
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $report = $this->leaveReport->build($year, $branchId, $departmentId);

        $pdf = Pdf::loadView('reports.pdf.leave', [
            'rows' => $report['rows'],
            'types' => $report['types'],
            'year' => $year,
            'branchName' => $branchId ? Branch::find($branchId)?->name : null,
            'departmentName' => $departmentId ? Department::find($departmentId)?->name : null,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rekap-cuti-'.$year.'.pdf');
    }

    /**
     * Per-employee leave history for the selected year.
     */
    public function employeeLeave(Request $request, Employee $employee): View
    {
        $year = $this->resolveYear($request->input('year'));

        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereYear('start_date', $year)
            ->with('leaveType')
            ->orderByDesc('start_date')
            ->get();

        return view('reports.leave-detail', [
            'employee' => $employee->load(['branch', 'department', 'jobPosition']),
            'requests' => $requests,
            'year' => $year,
            'approvedDays' => (int) $requests->where('status', LeaveRequestStatus::Approved)->sum(fn (LeaveRequest $r) => $r->days),
        ]);
    }

    private function resolveYear(?string $value): int
    {
        $year = (int) $value;

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
    }

    /**
     * @return array{0: Carbon, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function filters(Request $request): array
    {
        $month = $this->resolveMonth($request->input('month'));

        return [
            $month,
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
            $request->integer('branch_id') ?: null,
            $request->integer('department_id') ?: null,
        ];
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
