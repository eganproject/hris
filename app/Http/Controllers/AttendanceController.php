<?php

namespace App\Http\Controllers;

use App\Actions\ProcessDayAttendance;
use App\Enums\AttendanceStatus;
use App\Http\Requests\AttendancePunchRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceResolver;
use App\Support\DataScope;
use App\Support\MonthInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceResolver $resolver,
        private readonly ProcessDayAttendance $processDay,
    ) {}

    /**
     * Daily attendance board: every active employee's resolved status for a date,
     * alongside their scheduled shift so gaps ("belum diproses") are visible.
     */
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->input('date'));
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $search = $request->string('search')->toString() ?: null;
        $statusFilter = $request->string('status')->toString() ?: null;
        $perPage = min(max((int) $request->input('per_page', 50), 25), 200);
        $scope = DataScope::forTeam($request->user());

        $population = fn () => $scope->employees()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->byDepartment($departmentId)
            ->when($search, fn ($query, $s) => $query->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")));

        // Ringkasan dihitung di database atas seluruh populasi (lokasi + divisi +
        // pencarian), sebelum filter status menyempitkannya dan tanpa bergantung pada
        // halaman yang kebetulan sedang dibuka.
        $summary = Attendance::query()
            ->whereDate('work_date', $date->toDateString())
            ->whereIn('employee_id', $population()->select('employees.id'))
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        // Filter status juga dikerjakan di query, supaya paginasinya tidak menghasilkan
        // halaman yang setengah kosong.
        $employees = $population()
            ->when($statusFilter, fn ($query, $s) => $query->whereHas(
                'attendances',
                fn ($q) => $q->whereDate('work_date', $date->toDateString())->where('status', $s),
            ))
            ->with([
                'attendances' => fn ($query) => $query->whereDate('work_date', $date->toDateString())->with('shift'),
                'schedules' => fn ($query) => $query->whereDate('work_date', $date->toDateString())->with('shift'),
                // Dipakai kolom "Jadwal" untuk menandai hari WFH/dinas luar yang
                // berasal dari pengajuan, bukan dari roster.
                'leaveRequests' => fn ($query) => $query->approvedOn($date->toDateString())->with('leaveType'),
            ])
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.daily.index', [
            'employees' => $employees,
            'summary' => $summary,
            'date' => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'perPage' => $perPage,
            'hasNoScope' => $scope->isEmpty(),
            'hasNoTeam' => $scope->hasNoTeam(),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
            'statuses' => AttendanceStatus::options(),
        ]);
    }

    /**
     * Per-employee attendance history for one month, reachable by clicking an
     * employee on the daily board. Same scope gate as the board itself.
     */
    public function history(Request $request, Employee $employee): View
    {
        DataScope::forTeam($request->user())->authorize($employee);

        $month = MonthInput::resolve($request->input('month'));
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

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

        $worked = AttendanceStatus::workedValues();

        return view('attendance.daily.history', [
            'employee' => $employee->load(['branch', 'departments', 'jobPosition']),
            'records' => $records,
            'approvedOvertime' => $approvedOvertime,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branchId' => $request->integer('branch_id') ?: null,
            'summary' => [
                'total_hari' => $records->count(),
                'hadir' => $records->filter(fn ($r) => in_array($r->status?->value, $worked, true))->count(),
                'terlambat' => $records->filter(fn ($r) => $r->status?->value === 'late')->count(),
                'alfa' => $records->filter(fn ($r) => $r->status?->value === 'absent')->count(),
                'terlambat_menit' => (int) $records->sum('late_minutes'),
                'kerja_menit' => (int) $records->sum('work_minutes'),
                'lembur_menit' => (int) $approvedOvertime->sum(),
            ],
        ]);
    }

    /**
     * Resolve/refresh the whole date for the current scope. Existing punches are
     * preserved; unscheduled days become DayOff, scheduled-but-unpunched become Absent.
     */
    public function process(Request $request): RedirectResponse
    {
        $date = $this->resolveDate($request->input('date'));
        $branchId = $request->integer('branch_id') ?: null;

        // Only the employees this user may see get (re)processed.
        $count = $this->processDay->handle($date, $branchId, DataScope::forTeam($request->user()));

        return redirect()
            ->route('attendance.daily.index', $request->only('date', 'branch_id'))
            ->with('status', "Absensi {$date->translatedFormat('d M Y')} diproses ({$count} karyawan).");
    }

    /**
     * Manual punch entry/edit for one employee-day (stand-in for the fingerprint feed).
     */
    public function storePunch(AttendancePunchRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
        DataScope::forTeam($request->user())->authorize($employee);

        $date = Carbon::parse($request->date('work_date'));

        $this->resolver->resolve(
            $employee,
            $date,
            $request->string('clock_in')->toString() ?: null,
            $request->string('clock_out')->toString() ?: null,
            $request->string('note')->toString() ?: null,
        );

        return redirect()
            ->route('attendance.daily.index', ['date' => $date->toDateString(), 'branch_id' => $request->integer('branch_id') ?: null])
            ->with('status', 'Absensi karyawan diperbarui.');
    }

    private function resolveDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
