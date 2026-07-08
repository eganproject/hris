<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OvertimeApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class OvertimeController extends Controller
{
    /**
     * Overtime approval board: every day with computed overtime, plus its decision.
     */
    public function index(Request $request): View
    {
        $month = $this->resolveMonth($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;

        $attendances = Attendance::query()
            ->where('overtime_minutes', '>', 0)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $q->active()->when($branchId, fn ($q) => $q->where('branch_id', $branchId)))
            ->with(['employee', 'shift'])
            ->orderBy('work_date')
            ->get();

        $approvals = OvertimeApproval::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (OvertimeApproval $a) => $a->employee_id.'|'.$a->work_date->toDateString());

        return view('attendance.overtime.index', [
            'attendances' => $attendances,
            'approvals' => $approvals,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchId' => $branchId,
            'pendingCount' => $attendances->filter(fn ($a) => ! isset($approvals[$a->employee_id.'|'.$a->work_date->toDateString()]) || $approvals[$a->employee_id.'|'.$a->work_date->toDateString()]->status === 'pending')->count(),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $data = $this->validateDecision($request);
        $computed = $this->computedMinutes($data['employee_id'], $data['work_date']);
        $approved = $request->filled('approved_minutes') ? max(0, (int) $request->input('approved_minutes')) : $computed;

        OvertimeApproval::query()->updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            [
                'computed_minutes' => $computed,
                'approved_minutes' => $approved,
                'status' => OvertimeApproval::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'decided_at' => now(),
                'notes' => $request->string('notes')->toString() ?: null,
            ],
        );

        return redirect()->route('attendance.overtime.index', $request->only('month', 'branch_id'))->with('status', 'Lembur disetujui.');
    }

    public function reject(Request $request): RedirectResponse
    {
        $data = $this->validateDecision($request);

        OvertimeApproval::query()->updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            [
                'computed_minutes' => $this->computedMinutes($data['employee_id'], $data['work_date']),
                'approved_minutes' => 0,
                'status' => OvertimeApproval::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'decided_at' => now(),
                'notes' => $request->string('notes')->toString() ?: null,
            ],
        );

        return redirect()->route('attendance.overtime.index', $request->only('month', 'branch_id'))->with('status', 'Lembur ditolak.');
    }

    /**
     * Monthly overtime recap: approved overtime totals per employee.
     */
    public function recap(Request $request): View
    {
        $month = $this->resolveMonth($request->input('month'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $branchId = $request->integer('branch_id') ?: null;

        $rows = OvertimeApproval::query()
            ->approved()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $q->when($branchId, fn ($q) => $q->where('branch_id', $branchId)))
            ->selectRaw('employee_id, count(*) as days, sum(approved_minutes) as minutes')
            ->groupBy('employee_id')
            ->get();

        $employees = Employee::query()->whereIn('id', $rows->pluck('employee_id'))->orderBy('full_name')->get()->keyBy('id');

        return view('attendance.overtime.recap', [
            'rows' => $rows->sortByDesc('minutes')->values(),
            'employees' => $employees,
            'month' => $month,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchId' => $branchId,
            'totalMinutes' => (int) $rows->sum('minutes'),
        ]);
    }

    /**
     * @return array{employee_id: int, work_date: string}
     */
    private function validateDecision(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'approved_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return ['employee_id' => (int) $data['employee_id'], 'work_date' => Carbon::parse($data['work_date'])->toDateString()];
    }

    private function computedMinutes(int $employeeId, string $workDate): int
    {
        return (int) (Attendance::query()->where('employee_id', $employeeId)->where('work_date', $workDate)->value('overtime_minutes') ?? 0);
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
