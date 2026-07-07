<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

class LeaveBalanceService
{
    /**
     * Yearly entitlement in days: the employee's own allocation, else the leave
     * type's default. Non-quota types return 0 (treated as unlimited elsewhere).
     */
    public function quota(Employee $employee, LeaveType $type, int $year): int
    {
        if (! $type->counts_against_balance) {
            return 0;
        }

        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        return $balance?->quota_days ?? $type->default_quota_days ?? 0;
    }

    /**
     * Days already committed for the year — approved plus in-flight requests, so a
     * pending request can't be double-booked. Optionally excludes one request id.
     */
    public function used(Employee $employee, LeaveType $type, int $year, ?int $exceptId = null): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->holdsQuota()
            ->whereYear('start_date', $year)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->sum(fn (LeaveRequest $request) => $request->days);
    }

    public function remaining(Employee $employee, LeaveType $type, int $year, ?int $exceptId = null): int
    {
        return $this->quota($employee, $type, $year) - $this->used($employee, $type, $year, $exceptId);
    }
}
