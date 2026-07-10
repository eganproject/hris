<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel version of the per-employee attendance recap. Receives the already-computed
 * rows from AttendanceReport so the sheet matches the on-screen table exactly.
 */
class AttendanceReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function title(): string
    {
        return 'Rekap Kehadiran';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'Lokasi', 'Divisi', 'Jabatan',
            'Total Hari', 'Hadir', 'Terlambat', 'Pulang Cepat', 'Alfa', 'Cuti', 'Sakit',
            'Total Terlambat (menit)', 'Total Jam Kerja', 'Total Lembur',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        $employee = $row['employee'];

        return [
            $employee->employee_number,
            $employee->full_name,
            $employee->branch?->name ?? '-',
            $employee->department?->name ?? '-',
            $employee->jobPosition?->name ?? '-',
            $row['total_hari'],
            $row['hadir'],
            $row['terlambat'],
            $row['pulang_cepat'],
            $row['alfa'],
            $row['cuti'],
            $row['sakit'],
            $row['terlambat_menit'],
            $this->hm($row['kerja_menit']),
            $this->hm($row['lembur_menit']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    private function hm(int $minutes): string
    {
        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    }
}
