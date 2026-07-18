<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeApproval;
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
    public function rows(string $from, string $to, ?int $branchId = null, ?int $departmentId = null, ?DataScope $scope = null): Collection
    {
        $stats = Attendance::query()
            ->whereBetween('work_date', [$from, $to])
            ->whereHas('employee', function ($query) use ($branchId, $departmentId) {
                // Divisi cocok bila SALAH SATU divisi karyawan sama (home atau tambahan).
                $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($departmentId, fn ($q) => $q->byDepartment($departmentId));
            })
            ->when($scope, fn ($query) => $scope->constrain($query))
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
                -- "Hari" = hari kerja terjadwal; libur nasional & libur jadwal tidak
                -- dihitung agar breakdown (hadir+alfa+cuti+sakit) berdamai dengan totalnya.
                SUM(CASE WHEN status NOT IN ('holiday','day_off') THEN 1 ELSE 0 END) as total_hari
                SQL)
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        // Sertakan setiap karyawan aktif dalam cakupan yang sudah bekerja pada periode
        // ini — termasuk yang belum punya satu pun baris absensi (belum terjadwal / baru
        // masuk) — agar mereka tetap tampil bernilai nol, bukan hilang dari rekap.
        $employeeQuery = ($scope ? $scope->employees() : Employee::query())
            ->active()
            ->where(fn ($q) => $q->whereNull('join_date')->orWhereDate('join_date', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->byDepartment($departmentId));

        $employeeIds = $stats->keys()->merge($employeeQuery->pluck('id'))->unique()->values();

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        // Overtime shown in reports is the *approved* overtime (employee-submitted,
        // supervisor-approved) — the authoritative figure for payroll — not the raw
        // minutes the resolver detects from punches.
        $approvedOvertime = OvertimeApproval::query()
            ->approved()
            ->whereBetween('work_date', [$from, $to])
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, COALESCE(SUM(approved_minutes), 0) as menit')
            ->pluck('menit', 'employee_id');

        return Employee::query()
            ->whereIn('id', $employeeIds)
            ->with(['branch', 'department', 'departments', 'jobPosition'])
            ->orderBy('full_name')
            ->get()
            ->map(function (Employee $employee) use ($stats, $approvedOvertime) {
                // Null bila karyawan belum punya baris absensi pada periode ini.
                $s = $stats->get($employee->id);

                return [
                    'employee' => $employee,
                    'total_hari' => (int) ($s->total_hari ?? 0),
                    'hadir' => (int) ($s->hadir ?? 0),
                    'terlambat' => (int) ($s->terlambat ?? 0),
                    'pulang_cepat' => (int) ($s->pulang_cepat ?? 0),
                    'alfa' => (int) ($s->alfa ?? 0),
                    'cuti' => (int) ($s->cuti ?? 0),
                    'sakit' => (int) ($s->sakit ?? 0),
                    'terlambat_menit' => (int) ($s->terlambat_menit ?? 0),
                    'kerja_menit' => (int) ($s->kerja_menit ?? 0),
                    'lembur_menit' => (int) ($approvedOvertime[$employee->id] ?? 0),
                ];
            });
    }
}
