<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports active employees who have never been assigned a schedule pattern, using
 * the same filters and attendance data scope as the on-screen list so the file
 * never leaks employees the user cannot see.
 */
class UnscheduledEmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  User|null  $user  when given, limits the export to that user's attendance scope
     */
    public function __construct(private array $filters = [], private ?User $user = null) {}

    public function title(): string
    {
        return 'Belum Terjadwal';
    }

    /** @return Builder<Employee> */
    public function query(): Builder
    {
        $branchId = $this->filters['branch_id'] ?? null;
        $departmentId = $this->filters['department_id'] ?? null;
        $jobPositionId = $this->filters['job_position_id'] ?? null;
        $search = $this->filters['search'] ?? null;
        $noSchedule = ($this->filters['mode'] ?? null) === 'no_schedule';

        $month = $this->resolveMonth($this->filters['month'] ?? null);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        // Reuse the exact attendance scope so the export matches the list precisely.
        $base = $this->user
            ? DataScope::forAttendance($this->user)->employees()
            : Employee::query();

        return $base
            ->with(['branch', 'departments', 'jobPosition'])
            ->active()
            // Karyawan "jam kantor" sengaja tidak dijadwalkan — bukan bagian dari daftar.
            ->where('follows_office_hours', false)
            ->when(
                $noSchedule,
                fn ($q) => $q->whereDoesntHave('schedules', fn ($s) => $s->whereBetween('work_date', [$from, $to])),
                fn ($q) => $q->whereDoesntHave('scheduleAssignments'),
            )
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->byDepartment($departmentId))
            ->when($jobPositionId, fn ($q) => $q->where('job_position_id', $jobPositionId))
            ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$s}%")->orWhere('employee_number', 'like', "%{$s}%")))
            ->orderBy('full_name');
    }

    private function resolveMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Kode Karyawan',
            'Nama Karyawan',
            'Lokasi',
            'Divisi',
            'Jabatan',
            'Tgl Bergabung',
        ];
    }

    /**
     * @param  Employee  $employee
     * @return list<string|null>
     */
    public function map($employee): array
    {
        return [
            $employee->employee_number,
            $employee->full_name,
            $employee->branch?->name,
            $employee->departments->pluck('name')->implode(', '),
            $employee->jobPosition?->name,
            $employee->join_date?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
