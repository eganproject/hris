<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Per-employee yearly leave quota. An explicit LeaveBalance row is stored only when
 * the quota differs from the leave type's default_quota_days; otherwise the employee
 * simply inherits the default (so changing a default propagates automatically).
 */
class LeaveBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $year = $this->resolveYear($request->input('year'));
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;

        $types = LeaveType::query()
            ->where('is_active', true)
            ->where('counts_against_balance', true)
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->active()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with(['branch', 'department'])
            ->orderBy('full_name')
            ->get();

        $overrides = LeaveBalance::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('year', $year)
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy('leave_type_id'));

        return view('attendance.leave-balances.index', [
            'types' => $types,
            'employees' => $employees,
            'overrides' => $overrides,
            'year' => $year,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $year = $this->resolveYear($request->input('year'));

        $types = LeaveType::query()
            ->where('counts_against_balance', true)
            ->get()
            ->keyBy('id');

        /** @var array<int, array<int, mixed>> $quota */
        $quota = $request->input('quota', []);

        foreach ($quota as $employeeId => $perType) {
            foreach ((array) $perType as $typeId => $value) {
                $type = $types->get((int) $typeId);

                if (! $type) {
                    continue;
                }

                $default = (int) ($type->default_quota_days ?? 0);
                $entered = $value === '' || $value === null ? $default : (int) $value;
                $entered = max(0, min(365, $entered));

                // Store an override only when it differs from the default; otherwise
                // drop any existing override so the employee inherits the default.
                if ($entered === $default) {
                    LeaveBalance::query()
                        ->where(['employee_id' => $employeeId, 'leave_type_id' => $typeId, 'year' => $year])
                        ->delete();

                    continue;
                }

                LeaveBalance::query()->updateOrCreate(
                    ['employee_id' => $employeeId, 'leave_type_id' => $typeId, 'year' => $year],
                    ['quota_days' => $entered],
                );
            }
        }

        return redirect()
            ->route('attendance.leave-balances.index', $request->only('year', 'branch_id', 'department_id'))
            ->with('status', 'Kuota cuti berhasil disimpan.');
    }

    private function resolveYear(?string $value): int
    {
        $year = (int) $value;

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
    }
}
