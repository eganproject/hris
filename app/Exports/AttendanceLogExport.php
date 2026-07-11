<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel version of the daily attendance log: one row per attendance record with the
 * clock-in / clock-out times. Receives the already-fetched records so the sheet
 * matches the on-screen table exactly.
 */
class AttendanceLogExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function title(): string
    {
        return 'Log Absensi';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Tanggal', 'No. Karyawan', 'Nama', 'Divisi', 'Shift',
            'Jam Masuk', 'Jam Keluar', 'Terlambat (menit)', 'Pulang Cepat (menit)',
            'Jam Kerja', 'Status',
        ];
    }

    /**
     * @param  Attendance  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        return [
            $row->work_date->format('Y-m-d'),
            $row->employee?->employee_number ?? '-',
            $row->employee?->full_name ?? '-',
            $row->employee?->department?->name ?? '-',
            $row->shift?->code ?? '-',
            $row->clock_in?->format('H:i') ?? '-',
            $row->clock_out?->format('H:i') ?? '-',
            (int) $row->late_minutes,
            (int) $row->early_leave_minutes,
            $this->hm((int) $row->work_minutes),
            $row->status?->label() ?? '-',
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
