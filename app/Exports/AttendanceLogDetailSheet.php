<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Log harian mentah, urut per karyawan lalu per tanggal — satu orang terbaca utuh
 * sebagai satu blok. Kolom durasi dikirim sebagai angka (bukan "8j 30m") supaya bisa
 * dijumlahkan dan dirata-rata langsung di Excel.
 */
class AttendanceLogDetailSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use FormatsSheet;

    /**
     * @param  Collection<int, Attendance>  $rows  sudah diurutkan oleh pemanggil
     */
    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Log Harian';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'NIK', 'Nama', 'Divisi', 'Lokasi',
            'Tanggal', 'Hari', 'Shift',
            'Jam Masuk', 'Jam Keluar',
            'Terlambat (menit)', 'Pulang Cepat (menit)',
            'Jam Kerja (jam)', 'Lembur (jam)',
            'Status', 'Catatan',
        ];
    }

    /**
     * @param  Attendance  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->employee?->employee_number ?? '-',
            $row->employee?->full_name ?? '-',
            $row->employee?->departments->pluck('name')->implode(', ') ?: '-',
            $row->employee?->branch?->name ?? '-',
            $row->work_date->format('Y-m-d'),
            $row->work_date->translatedFormat('l'),
            $row->shift?->code ?? '-',
            $row->clock_in?->format('H:i') ?? '',
            $row->clock_out?->format('H:i') ?? '',
            (int) $row->late_minutes,
            (int) $row->early_leave_minutes,
            round($row->work_minutes / 60, 2),
            round($row->overtime_minutes / 60, 2),
            $row->status?->label() ?? '-',
            $row->note ?? '',
        ];
    }
}
