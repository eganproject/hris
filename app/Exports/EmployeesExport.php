<?php

namespace App\Exports;

use App\Imports\EmployeesImport;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports employees to an .xlsx whose columns mirror the import template exactly,
 * so an exported file can be tweaked and re-imported. Optional filters (branch,
 * department, status, search) narrow the export to the current list view.
 */
class EmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  User|null  $user  when given, the export is limited to that user's data scope
     */
    public function __construct(private array $filters = [], private ?User $user = null) {}

    public function title(): string
    {
        return 'Data Karyawan';
    }

    /** @return Builder<Employee> */
    public function query(): Builder
    {
        return Employee::query()
            ->with(['branch', 'department', 'jobPosition', 'manager', 'currentContract', 'deviceMappings'])
            ->when($this->user, fn ($q) => $q->visibleTo($this->user))
            ->byBranch($this->filters['branch_id'] ?? null)
            ->byDepartment($this->filters['department_id'] ?? null)
            ->when($this->filters['status'] ?? null, fn ($q, $status) => $q->where('employment_status', $status))
            ->when($this->filters['exit_reason'] ?? null, fn ($q, $reason) => $q->where('exit_reason', $reason))
            ->when($this->filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('join_date');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return array_map(fn ($column) => $column['header'], EmployeesImport::columns());
    }

    /**
     * @param  Employee  $employee
     * @return list<string|null>
     */
    public function map($employee): array
    {
        $contract = $employee->currentContract;
        $globalPin = $employee->deviceMappings->firstWhere('device_id', null)?->machine_user_id;

        return [
            $employee->employee_number,
            $employee->full_name,
            $employee->email,
            $employee->phone,
            $employee->identity_number,
            $employee->birth_date?->format('Y-m-d'),
            $employee->join_date?->format('Y-m-d'),
            $this->employmentStatusLabel($employee->employment_status),
            $employee->follows_office_hours ? 'Ya' : 'Tidak',
            $employee->address,
            $employee->branch?->name,
            $employee->department?->name,
            $employee->jobPosition?->name,
            $employee->manager?->employee_number,
            $contract?->contract_number,
            $contract?->contract_type,
            $contract?->start_date?->format('Y-m-d'),
            $contract?->end_date?->format('Y-m-d'),
            $contract ? (EmployeeContract::statusLabels()[$contract->status] ?? $contract->status) : null,
            $contract?->notes,
            $globalPin,
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

    private function employmentStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => $status,
        };
    }
}
