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
            ->when($departmentId, fn ($q) => $q->byDepartment($departmentId))
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

    /**
     * Riwayat cuti satu karyawan dalam setahun, beserta saldo per jenis cuti.
     *
     * "Sisa" memakai aturan yang sama dengan build(): kuota dikurangi cuti yang
     * DISETUJUI. Pengajuan yang masih menunggu ditampilkan terpisah, supaya Detail
     * Cuti dan Rekap Cuti tidak pernah menyebut sisa yang berbeda untuk orang yang
     * sama.
     *
     * @return array{
     *     requests: Collection<int, LeaveRequest>,
     *     balances: Collection<int, array{type: LeaveType, quota: ?int, used: int, pending: int, remaining: ?int}>,
     *     remainingAfter: array<int, int>,
     * }
     */
    public function employeeHistory(Employee $employee, int $year): array
    {
        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereYear('start_date', $year)
            ->with('leaveType')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $overrides = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->pluck('quota_days', 'leave_type_id');

        // Jenis berkuota selalu tampil — sisa yang masih utuh pun informasi. Jenis
        // lain hanya bila memang pernah diajukan pada tahun itu.
        $types = LeaveType::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('is_active', true)->where('counts_against_balance', true))
                ->orWhereIn('id', $requests->pluck('leave_type_id')->unique()))
            ->orderBy('name')
            ->get();

        $days = fn (Collection $rows): int => (int) $rows->sum(fn (LeaveRequest $r) => $r->days);

        $balances = $types->map(function (LeaveType $type) use ($requests, $overrides, $days) {
            $ofType = $requests->where('leave_type_id', $type->id);
            $used = $days($ofType->filter(fn (LeaveRequest $r) => $r->status === LeaveRequestStatus::Approved));
            $quota = $type->counts_against_balance
                ? (int) ($overrides[$type->id] ?? $type->default_quota_days ?? 0)
                : null;

            return [
                'type' => $type,
                'quota' => $quota,
                'used' => $used,
                'pending' => $days($ofType->filter(fn (LeaveRequest $r) => $r->status->isPending())),
                'remaining' => $quota === null ? null : $quota - $used,
            ];
        })->values();

        // Sisa setelah tiap cuti disetujui, dijalankan urut tanggal dari awal tahun —
        // sehingga baris riwayat terbaca seperti buku saldo.
        $running = $balances
            ->filter(fn (array $balance) => $balance['quota'] !== null)
            ->mapWithKeys(fn (array $balance) => [$balance['type']->id => $balance['quota']])
            ->all();

        $remainingAfter = [];

        $requests
            ->filter(fn (LeaveRequest $r) => $r->status === LeaveRequestStatus::Approved && isset($running[$r->leave_type_id]))
            ->sortBy(fn (LeaveRequest $r) => $r->start_date->format('Y-m-d').sprintf('-%010d', $r->id))
            ->each(function (LeaveRequest $r) use (&$running, &$remainingAfter) {
                $running[$r->leave_type_id] -= $r->days;
                $remainingAfter[$r->id] = $running[$r->leave_type_id];
            });

        return ['requests' => $requests, 'balances' => $balances, 'remainingAfter' => $remainingAfter];
    }
}
