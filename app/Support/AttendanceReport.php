<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Builds the per-employee attendance recap for a date range: how many days each
 * employee was present, late, left early, absent, on leave/sick, plus total late,
 * worked and overtime minutes. Shared by the report screen and its Excel export so
 * both always show identical numbers.
 */
class AttendanceReport
{
    /**
     * @return Collection<int, array{
     *     employee: Employee, total_hari:int, hadir:int, terlambat:int,
     *     pulang_cepat:int, alfa:int, cuti:int, sakit:int,
     *     terlambat_menit:int, kerja_menit:int, lembur_menit:int
     * }>
     */
    public function rows(string $from, string $to, ?int $branchId = null, ?int $departmentId = null): Collection
    {
        $stats = Attendance::query()
            ->whereBetween('work_date', [$from, $to])
            ->whereHas('employee', function ($query) use ($branchId, $departmentId) {
                $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));
            })
            ->selectRaw(<<<'SQL'
                employee_id,
                SUM(CASE WHEN status IN ('present','late','early_leave','wfh','business_trip') THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as terlambat,
                SUM(CASE WHEN status = 'early_leave' THEN 1 ELSE 0 END) as pulang_cepat,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as alfa,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as cuti,
                SUM(CASE WHEN status = 'sick' THEN 1 ELSE 0 END) as sakit,
                COALESCE(SUM(late_minutes), 0) as terlambat_menit,
                COALESCE(SUM(work_minutes), 0) as kerja_menit,
                COALESCE(SUM(overtime_minutes), 0) as lembur_menit,
                COUNT(*) as total_hari
                SQL)
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        if ($stats->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->whereIn('id', $stats->keys())
            ->with(['branch', 'department', 'jobPosition'])
            ->orderBy('full_name')
            ->get()
            ->map(function (Employee $employee) use ($stats) {
                $s = $stats[$employee->id];

                return [
                    'employee' => $employee,
                    'total_hari' => (int) $s->total_hari,
                    'hadir' => (int) $s->hadir,
                    'terlambat' => (int) $s->terlambat,
                    'pulang_cepat' => (int) $s->pulang_cepat,
                    'alfa' => (int) $s->alfa,
                    'cuti' => (int) $s->cuti,
                    'sakit' => (int) $s->sakit,
                    'terlambat_menit' => (int) $s->terlambat_menit,
                    'kerja_menit' => (int) $s->kerja_menit,
                    'lembur_menit' => (int) $s->lembur_menit,
                ];
            });
    }
}
