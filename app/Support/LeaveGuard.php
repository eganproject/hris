<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class LeaveGuard
{
    /**
     * Shared business rules for a leave request: no overlapping request and enough
     * remaining quota. Adds validation errors instead of throwing.
     */
    public static function check(Validator $validator, Employee $employee, int $leaveTypeId, string $start, string $end, ?int $exceptId = null): void
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->holdsQuota()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlaps) {
            $validator->errors()->add('start_date', 'Sudah ada pengajuan cuti/izin yang beririsan dengan rentang tanggal ini.');
        }

        $type = LeaveType::find($leaveTypeId);

        if ($type && $type->counts_against_balance) {
            $days = (int) $startDate->diffInDays($endDate) + 1;
            $remaining = app(LeaveBalanceService::class)->remaining($employee, $type, (int) $startDate->year, $exceptId);

            if ($days > $remaining) {
                $validator->errors()->add(
                    'leave_type_id',
                    "Sisa saldo {$type->name} tidak cukup: sisa {$remaining} hari, diminta {$days} hari.",
                );
            }
        }
    }
}
