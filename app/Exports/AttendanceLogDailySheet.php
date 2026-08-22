<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Rekap per tanggal: memperlihatkan hari mana yang bermasalah — lonjakan alfa,
 * keterlambatan massal, atau hari dengan jam kerja anjlok — tanpa perlu membaca
 * log baris per baris.
 */
class AttendanceLogDailySheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use FormatsSheet;

    public function __construct(private readonly AttendanceLogSummary $summary) {}

    public function title(): string
    {
        return 'Rekap Harian';
    }

    public function collection(): Collection
    {
        return $this->summary->perDate();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Tanggal', 'Hari', 'Karyawan Tercatat',
            'Hadir', '% Kehadiran', 'Tepat Waktu', 'Terlambat', 'Pulang Cepat', 'Alfa',
            'Cuti / Izin', 'Sakit', 'WFH', 'Dinas Luar', 'Libur',
            'Jam Kerja (jam)', 'Total Telat (menit)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row['tanggal']->format('Y-m-d'),
            $row['hari'],
            $row['karyawan'],
            $row['hadir'],
            $row['persen_kehadiran'],
            $row['tepat_waktu'],
            $row['terlambat'],
            $row['pulang_cepat'],
            $row['alfa'],
            $row['cuti'],
            $row['sakit'],
            $row['wfh'],
            $row['dinas_luar'],
            $row['libur'],
            $row['jam_kerja'],
            $row['telat_menit'],
        ];
    }
}
