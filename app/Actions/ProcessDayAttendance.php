<?php

namespace App\Actions;

use App\Models\Employee;
use App\Services\AttendanceResolver;
use App\Support\DataScope;
use Carbon\CarbonInterface;

/**
 * Single source of truth for "resolve a whole day of attendance": used by both the
 * manual "Proses" button (AttendanceController) and the nightly scheduled command,
 * so both always produce identical results.
 *
 * For every active employee on the date it re-runs the resolver, which keeps any
 * punches already recorded and fills the gaps: scheduled-but-unpunched → Absent,
 * approved leave → its leave status, holidays → Holiday, unscheduled → DayOff.
 * Idempotent — safe to run repeatedly.
 */
class ProcessDayAttendance
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    /**
     * @param  DataScope|null  $scope  when given, only employees inside it are processed
     *                                 (the nightly command passes none: it runs for everyone)
     */
    public function handle(CarbonInterface $date, ?int $branchId = null, ?DataScope $scope = null): int
    {
        // Cakupannya diterima jadi, bukan diturunkan sendiri dari penggunanya: tombol
        // "Proses Ulang" harus memproses persis orang-orang yang tampil di layar
        // pemakainya, bukan himpunan yang lebih luas.
        $employees = Employee::query()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($scope, fn ($query) => $query->whereIn('id', $scope->employees()->select('employees.id')))
            ->get();

        foreach ($employees as $employee) {
            $this->resolver->reprocess($employee, $date);
        }

        return $employees->count();
    }
}
