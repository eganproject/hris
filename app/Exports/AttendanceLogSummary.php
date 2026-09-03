<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Perhitungan bersama untuk seluruh sheet ekspor log absensi: sekali menghitung dari
 * koleksi baris yang sudah dimuat, lalu dipakai ulang oleh sheet ringkasan karyawan
 * dan rekap harian. Menaruhnya di satu tempat menjamin angka di kedua sheet tidak
 * pernah berbeda meski dikelompokkan dengan cara yang berbeda.
 */
class AttendanceLogSummary
{
    /** Status yang berarti orangnya memang tidak dijadwalkan bekerja. */
    private const NON_WORKING = [AttendanceStatus::Holiday, AttendanceStatus::DayOff];

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function __construct(private readonly Collection $rows) {}

    /**
     * Satu baris per karyawan, urut nama — cocok untuk pivot atau ditempel ke
     * lampiran penggajian.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perEmployee(): Collection
    {
        return $this->rows
            ->groupBy('employee_id')
            ->map(function (Collection $rows) {
                /** @var Employee|null $employee */
                $employee = $rows->first()->employee;

                return [
                    'nik' => $employee?->employee_number,
                    'nama' => $employee?->full_name,
                    'divisi' => $employee?->departments->pluck('name')->implode(', ') ?: null,
                    'lokasi' => $employee?->branch?->name,
                    'jabatan' => $employee?->jobPosition?->name,
                    ...$this->tally($rows),
                ];
            })
            ->sortBy(fn (array $row) => strtolower((string) $row['nama']))
            ->values();
    }

    /**
     * Satu baris per tanggal — dipakai untuk melihat hari mana yang bermasalah
     * (lonjakan alfa atau keterlambatan) tanpa membaca log baris per baris.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perDate(): Collection
    {
        return $this->rows
            ->groupBy(fn (Attendance $row) => $row->work_date->toDateString())
            ->map(fn (Collection $rows, string $date) => [
                'tanggal' => $rows->first()->work_date,
                'hari' => $rows->first()->work_date->translatedFormat('l'),
                'karyawan' => $rows->pluck('employee_id')->unique()->count(),
                ...$this->tally($rows),
            ])
            ->sortBy('tanggal')
            ->values();
    }

    public function employeeCount(): int
    {
        return $this->rows->pluck('employee_id')->unique()->count();
    }

    /**
     * Hitungan status dan total menit untuk sekumpulan baris apa pun.
     *
     * @param  Collection<int, Attendance>  $rows
     * @return array<string, int|float>
     */
    private function tally(Collection $rows): array
    {
        $countOf = fn (AttendanceStatus $status) => $rows->where('status', $status)->count();

        // "Hari kerja" tidak menghitung libur nasional & libur jadwal, supaya
        // persentase kehadiran tidak terdilusi akhir pekan.
        $workingDays = $rows->reject(fn (Attendance $row) => in_array($row->status, self::NON_WORKING, true))->count();
        $worked = $rows->filter(fn (Attendance $row) => (bool) $row->status?->isWorked())->count();
        $lateMinutes = (int) $rows->sum('late_minutes');
        $lateDays = $rows->where('late_minutes', '>', 0)->count();

        return [
            'hari_tercatat' => $rows->count(),
            'hari_kerja' => $workingDays,
            'hadir' => $worked,
            'tepat_waktu' => $countOf(AttendanceStatus::Present),
            'terlambat' => $countOf(AttendanceStatus::Late),
            'pulang_cepat' => $countOf(AttendanceStatus::EarlyLeave),
            'alfa' => $countOf(AttendanceStatus::Absent),
            'cuti' => $countOf(AttendanceStatus::Leave),
            'izin' => $countOf(AttendanceStatus::Permit),
            'sakit' => $countOf(AttendanceStatus::Sick),
            'wfh' => $countOf(AttendanceStatus::Wfh),
            'dinas_luar' => $countOf(AttendanceStatus::BusinessTrip),
            'libur' => $countOf(AttendanceStatus::Holiday) + $countOf(AttendanceStatus::DayOff),
            'jam_kerja' => $this->hours((int) $rows->sum('work_minutes')),
            'jam_lembur' => $this->hours((int) $rows->sum('overtime_minutes')),
            'telat_menit' => $lateMinutes,
            // Rata-rata dihitung hanya atas hari yang benar-benar telat; dibagi seluruh
            // hari kerja, angkanya akan tampak kecil dan menyesatkan.
            'telat_menit_rata2' => $lateDays > 0 ? round($lateMinutes / $lateDays, 1) : 0,
            'persen_kehadiran' => $workingDays > 0 ? round($worked / $workingDays * 100, 1) : 0,
        ];
    }

    private function hours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }
}
