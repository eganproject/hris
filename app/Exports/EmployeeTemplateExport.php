<?php

namespace App\Exports;

use App\Imports\EmployeesImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The blank import template: one "Data Karyawan" sheet with just the header row to
 * fill in, plus a "Petunjuk Pengisian" sheet documenting every column, whether it
 * is required, and an example value. Columns come from EmployeesImport so the
 * template can never drift from what the importer actually accepts.
 */
class EmployeeTemplateExport implements WithMultipleSheets
{
    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            $this->dataSheet(),
            $this->guideSheet(),
        ];
    }

    private function dataSheet(): object
    {
        return new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
        {
            public function array(): array
            {
                return []; // header-only: no example rows to delete before importing
            }

            /** @return list<string> */
            public function headings(): array
            {
                return array_map(fn ($column) => $column['header'], EmployeesImport::columns());
            }

            public function title(): string
            {
                return 'Data Karyawan';
            }

            /** @return array<int, array<string, mixed>> */
            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }

    private function guideSheet(): object
    {
        return new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
        {
            /** @return list<list<string>> */
            public function array(): array
            {
                return array_map(fn ($column) => [
                    $column['header'],
                    $column['required'] ? 'Wajib' : 'Opsional',
                    $column['desc'],
                    $column['example'],
                ], EmployeesImport::columns());
            }

            /** @return list<string> */
            public function headings(): array
            {
                return ['Kolom', 'Wajib / Opsional', 'Keterangan', 'Contoh Isi'];
            }

            public function title(): string
            {
                return 'Petunjuk Pengisian';
            }

            /** @return array<int, array<string, mixed>> */
            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }
}
