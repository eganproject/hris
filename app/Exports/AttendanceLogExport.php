<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas Excel log absensi: bukan satu tabel mentah, melainkan lima lembar yang
 * menjawab pertanyaan berbeda — ringkasan per orang untuk penggajian dan evaluasi,
 * log harian untuk penelusuran kasus, rekap per tanggal untuk melihat pola, info shift
 * sebagai kunci pembacaan angka telat & jam kerja, dan lembar keterangan supaya berkas
 * yang beredar lewat email tidak kehilangan konteks.
 *
 * Urutannya sengaja dari yang paling sering dibuka ke yang paling jarang.
 */
class AttendanceLogExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, Attendance>  $rows  sudah difilter & diurutkan per karyawan lalu tanggal
     * @param  array<string, mixed>  $meta  filter yang dipakai, untuk lembar keterangan
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $meta = [],
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        $summary = new AttendanceLogSummary($this->rows);

        return [
            new AttendanceLogEmployeeSheet($summary),
            new AttendanceLogDetailSheet($this->rows),
            new AttendanceLogDailySheet($summary),
            new AttendanceLogShiftSheet($this->rows),
            new AttendanceLogInfoSheet($this->meta, $this->rows->count(), $summary->employeeCount()),
        ];
    }
}
