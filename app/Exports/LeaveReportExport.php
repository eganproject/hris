<?php

namespace App\Exports;

use App\Models\LeaveType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel version of the yearly leave recap. Leave types are dynamic columns; a
 * balance-counting type gets both a "Pakai" and a "Sisa" column, others just "Pakai".
 */
class LeaveReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, LeaveType>  $types
     */
    public function __construct(private Collection $rows, private Collection $types) {}

    public function title(): string
    {
        return 'Rekap Cuti';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        $headings = ['No. Karyawan', 'Nama', 'Lokasi', 'Divisi'];

        foreach ($this->types as $type) {
            $headings[] = $type->name.' (Pakai)';

            if ($type->counts_against_balance) {
                $headings[] = $type->name.' (Sisa)';
            }
        }

        $headings[] = 'Total Hari';

        return $headings;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        $employee = $row['employee'];

        $line = [
            $employee->employee_number,
            $employee->full_name,
            $employee->branch?->name ?? '-',
            $employee->department?->name ?? '-',
        ];

        foreach ($this->types as $type) {
            $cell = $row['cells'][$type->id];
            $line[] = $cell['used'];

            if ($type->counts_against_balance) {
                $line[] = $cell['remaining'];
            }
        }

        $line[] = $row['total'];

        return $line;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
