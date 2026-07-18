<?php

namespace App\Exports;

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
 * Exports the cross-employee contract list to an .xlsx, honouring the same
 * filters (range/status, location, division, type, search) and data scope as
 * the on-screen list so the file never leaks contracts the user cannot see.
 */
class ContractsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  User|null  $user  when given, limits the export to that user's data scope
     */
    public function __construct(private array $filters = [], private ?User $user = null) {}

    public function title(): string
    {
        return 'Kontrak Karyawan';
    }

    /** @return Builder<EmployeeContract> */
    public function query(): Builder
    {
        $query = EmployeeContract::query()
            ->with(['employee.branch', 'employee.departments', 'employee.jobPosition'])
            ->whereHas('employee', function ($employee) {
                if ($this->user) {
                    $employee->visibleTo($this->user);
                }

                $employee
                    ->byBranch($this->filters['branch_id'] ?? null)
                    ->byDepartment($this->filters['department_id'] ?? null);
            })
            ->when($this->filters['type'] ?? null, fn ($q, $type) => $q->where('contract_type', $type))
            ->when($this->filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($employee) => $employee
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%"));
                });
            });

        $query = match ($this->filters['filter'] ?? 'all') {
            'active' => $query->active(),
            'expiring_30' => $query->expiringWithin(30),
            'expiring_60' => $query->expiringWithin(60),
            'expiring_90' => $query->expiringWithin(90),
            'expired' => $query->lapsed(),
            default => $query,
        };

        return $query->orderByRaw('end_date is null')->orderBy('end_date');
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
            'Nomor Kontrak',
            'Jenis',
            'Mulai',
            'Selesai',
            'Status',
            'Sisa Hari',
            'Catatan',
        ];
    }

    /**
     * @param  EmployeeContract  $contract
     * @return list<string|int|null>
     */
    public function map($contract): array
    {
        $employee = $contract->employee;

        return [
            $employee?->employee_number,
            $employee?->full_name,
            $employee?->branch?->name,
            $employee?->departments->pluck('name')->implode(', '),
            $employee?->jobPosition?->name,
            $contract->contract_number,
            $contract->contract_type,
            $contract->start_date?->format('Y-m-d'),
            $contract->end_date?->format('Y-m-d') ?? 'Tidak terbatas',
            $contract->effective_status_label,
            is_null($contract->remaining_days) ? '' : $contract->remaining_days,
            $contract->notes,
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
