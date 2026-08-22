<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Jam kerja tiap shift yang benar-benar muncul di data yang diekspor — bukan seluruh
 * master shift. Tanpa lembar ini, kolom "Terlambat" dan "Jam Kerja" di log harian
 * menggantung: pembacanya tidak tahu jam berapa shift itu seharusnya mulai, berapa
 * lama istirahatnya, atau berapa menit toleransi yang dipakai saat menandai telat.
 */
class AttendanceLogShiftSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use FormatsSheet;

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Info Shift';
    }

    /**
     * Satu baris per shift, plus satu baris penutup untuk hari-hari tanpa shift
     * (libur, cuti, atau hari yang belum terjadwal) supaya jumlahnya berdamai dengan
     * total baris di log harian.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        $grouped = $this->rows->groupBy(fn (Attendance $row) => $row->shift_id ?? 0);

        $shifts = $grouped
            ->filter(fn (Collection $rows, $shiftId) => (int) $shiftId !== 0)
            ->map(fn (Collection $rows) => $this->describe($rows->first()->shift, $rows))
            ->sortBy('kode')
            ->values();

        $withoutShift = $grouped->get(0);

        return $withoutShift
            ? $shifts->push($this->describe(null, $withoutShift))
            : $shifts;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Kode', 'Nama Shift', 'Jam Mulai', 'Jam Selesai', 'Lintas Tengah Malam',
            'Durasi Shift (jam)', 'Istirahat (menit)', 'Jam Kerja Efektif (jam)',
            'Toleransi Telat (menit)', 'Toleransi Pulang Cepat (menit)',
            'Lembur Dihitung Setelah (menit)', 'Minimum Lembur (menit)',
            'Hari Tercatat', 'Jumlah Karyawan',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row['kode'],
            $row['nama'],
            $row['mulai'],
            $row['selesai'],
            $row['lintas_malam'],
            $row['durasi_jam'],
            $row['istirahat'],
            $row['efektif_jam'],
            $row['toleransi_telat'],
            $row['toleransi_pulang_cepat'],
            $row['lembur_setelah'],
            $row['lembur_minimum'],
            $row['hari'],
            $row['karyawan'],
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     * @return array<string, mixed>
     */
    private function describe(?Shift $shift, Collection $rows): array
    {
        $counts = [
            'hari' => $rows->count(),
            'karyawan' => $rows->pluck('employee_id')->unique()->count(),
        ];

        if (! $shift) {
            return [
                'kode' => '(tanpa shift)',
                'nama' => 'Hari tanpa shift terjadwal — libur, cuti, atau belum dijadwalkan',
                'mulai' => '-', 'selesai' => '-', 'lintas_malam' => '-',
                'durasi_jam' => '', 'istirahat' => '', 'efektif_jam' => '',
                'toleransi_telat' => '', 'toleransi_pulang_cepat' => '',
                'lembur_setelah' => '', 'lembur_minimum' => '',
                ...$counts,
            ];
        }

        // windowFor() sudah menangani shift yang menyeberang tengah malam, jadi
        // durasinya benar tanpa perhitungan khusus di sini.
        $window = $shift->windowFor(now()->startOfDay());
        $durationMinutes = (int) round($window['end']->getTimestamp() - $window['start']->getTimestamp()) / 60;
        $break = (int) $shift->break_minutes;

        return [
            'kode' => $shift->code,
            'nama' => $shift->name,
            'mulai' => substr((string) $shift->start_time, 0, 5),
            'selesai' => substr((string) $shift->end_time, 0, 5),
            'lintas_malam' => $shift->crosses_midnight ? 'Ya' : 'Tidak',
            'durasi_jam' => round($durationMinutes / 60, 2),
            'istirahat' => $break,
            'efektif_jam' => round(max(0, $durationMinutes - $break) / 60, 2),
            'toleransi_telat' => (int) $shift->late_tolerance_minutes,
            'toleransi_pulang_cepat' => (int) $shift->early_leave_tolerance_minutes,
            'lembur_setelah' => (int) $shift->overtime_starts_after_minutes,
            'lembur_minimum' => (int) $shift->overtime_min_minutes,
            ...$counts,
        ];
    }
}
