<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

/**
 * Sheet pertama yang dilihat HR: satu baris per karyawan untuk seluruh periode.
 * Semua angkanya numerik agar bisa langsung disortir, di-pivot, atau dijumlahkan.
 */
class AttendanceLogEmployeeSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use FormatsSheet;

    public function __construct(private readonly AttendanceLogSummary $summary) {}

    public function title(): string
    {
        return 'Ringkasan Karyawan';
    }

    public function collection(): Collection
    {
        return $this->summary->perEmployee();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'NIK', 'Nama', 'Divisi', 'Lokasi', 'Jabatan',
            'Hari Tercatat', 'Hari Kerja', 'Hadir', '% Kehadiran',
            'Tepat Waktu', 'Terlambat', 'Pulang Cepat', 'Alfa',
            'Cuti', 'Izin', 'Sakit', 'WFH', 'Dinas Luar', 'Libur',
            'Jam Kerja (jam)', 'Lembur (jam)',
            'Total Telat (menit)', 'Rata-rata Telat (menit)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row['nik'] ?? '-',
            $row['nama'] ?? '-',
            $row['divisi'] ?? '-',
            $row['lokasi'] ?? '-',
            $row['jabatan'] ?? '-',
            $row['hari_tercatat'],
            $row['hari_kerja'],
            $row['hadir'],
            $row['persen_kehadiran'],
            $row['tepat_waktu'],
            $row['terlambat'],
            $row['pulang_cepat'],
            $row['alfa'],
            $row['cuti'],
            $row['izin'],
            $row['sakit'],
            $row['wfh'],
            $row['dinas_luar'],
            $row['libur'],
            $row['jam_kerja'],
            $row['jam_lembur'],
            $row['telat_menit'],
            $row['telat_menit_rata2'],
        ];
    }
}
