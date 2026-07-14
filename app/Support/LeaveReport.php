<?php

namespace App\Support;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Collection;

/**
 * Per-employee yearly leave recap: approved days taken per leave type, plus the
 * remaining quota for types that count against a balance. Columns are the active
 * leave types (dynamic). Built in a couple of queries so it scales past a per-cell
 * lookup, and shared by the report screen and its Excel export.
 */
class LeaveReport
{
    /**
     * @return array{types: Collection<int, LeaveType>, rows: Collection<int, array<string, mixed>>}
     */
    public function build(int $year, ?int $branchId = null, ?int $departmentId = null, ?DataScope $scope = null): array
    {
        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();

        $employees = ($scope?->employees() ?? Employee::query())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with(['branch', 'department'])
            ->orderBy('full_name')
            ->get();

        $employeeIds = $employees->pluck('id');

        // Approved leave in the year, grouped per employee → per type → summed days.
        $approvedByEmployee = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereYear('start_date', $year)
            ->get()
            ->groupBy('employee_id');

        // Per-employee quota overrides for the year.
        $balancesByEmployee = LeaveBalance::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('year', $year)
            ->get()
            ->groupBy('employee_id');

        $rows = $employees->map(function (Employee $employee) use ($types, $approvedByEmployee, $balancesByEmployee) {
            $usedByType = ($approvedByEmployee[$employee->id] ?? collect())
                ->groupBy('leave_type_id')
                ->map(fn (Collection $requests) => (int) $requests->sum(fn (LeaveRequest $r) => $r->days));

            $quotaByType = ($balancesByEmployee[$employee->id] ?? collect())
                ->keyBy('leave_type_id');

            $cells = [];
            $total = 0;

            foreach ($types as $type) {
                $used = (int) ($usedByType[$type->id] ?? 0);
                $total += $used;

                $quota = $type->counts_against_balance
                    ? (int) ($quotaByType[$type->id]->quota_days ?? $type->default_quota_days ?? 0)
                    : null;

                $cells[$type->id] = [
                    'used' => $used,
                    'quota' => $quota,
                    'remaining' => $quota === null ? null : $quota - $used,
                ];
            }

            return [
                'employee' => $employee,
                'cells' => $cells,
                'total' => $total,
            ];
        });

        return ['types' => $types, 'rows' => $rows];
    }
}
