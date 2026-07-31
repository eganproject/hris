<?php

namespace App\Exports;

use App\Imports\ScheduleMatrixImport;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * The instructions sheet accompanying the roster template: what a cell may
 * contain, what happens on import, and the shift codes currently available.
 * Everything it documents is read from ScheduleMatrixImport's constants so the
 * guide cannot drift from what the importer actually accepts.
 */
class ScheduleTemplateGuideSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    /** Row heading the shift-code listing, filled in by array(). */
    private const CODE_LIST_HEADING = 'KODE SHIFT AKTIF';

    /**
     * @param  EloquentCollection<int, Shift>  $shifts
     */
    public function __construct(
        private readonly CarbonImmutable $month,
        private readonly EloquentCollection $shifts,
    ) {}

    public function title(): string
    {
        return 'Petunjuk';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Hal', 'Keterangan'];
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        $offTokens = implode(' / ', array_map(fn (string $token) => '"'.$token.'"', ScheduleMatrixImport::DAY_OFF_TOKENS));
        $firstCode = mb_strtoupper((string) ($this->shifts->first()?->code ?? 'P'));
        $wfh = ScheduleMatrixImport::WFH_SUFFIX;

        $rows = [
            ['Periode', 'Sel B1 pada sheet "Jadwal" menentukan bulan yang diimpor ('.$this->month->translatedFormat('F Y').'). Jangan diubah kecuali Anda memang ingin mengisi bulan lain — mengubahnya akan menimpa roster bulan tersebut.'],
            ['Baris judul', 'Baris 2 berisi "'.ScheduleMatrixImport::COLUMN_EMPLOYEE_NUMBER.'", "'.ScheduleMatrixImport::COLUMN_EMPLOYEE_NAME.'", lalu angka tanggal 1 sampai '.$this->month->daysInMonth.'. Jangan mengubah atau menghapus baris ini.'],
            ['Data karyawan', 'Mulai baris 3, satu baris untuk satu karyawan. Baris sudah terisi otomatis — jangan mengubah kolom Nomor Karyawan.'],
            ['Isi sel: kode shift', 'Tulis kode shift untuk menandai karyawan masuk pada tanggal itu, contoh "'.$firstCode.'". Daftar kode ada di bawah.'],
            ['Isi sel: libur', 'Tulis '.$offTokens.' untuk menandai hari libur / tidak masuk jadwal.'],
            ['Isi sel: WFH', 'Tambahkan "/'.$wfh.'" di belakang kode shift untuk kerja dari rumah, contoh "'.$firstCode.'/'.$wfh.'".'],
            ['Sel kosong', 'Jadwal hari itu TIDAK diubah. Kosongkan sel yang memang tidak ingin Anda sentuh.'],
            ['Kolom berwarna merah', 'Hari Sabtu/Minggu dan hari libur nasional. Hanya penanda visual — karyawan tetap boleh dijadwalkan di hari tersebut.'],
            ['Jika ada yang salah', 'Seluruh import dibatalkan dan tidak ada jadwal yang tersimpan. Daftar kesalahan ditampilkan dan bisa diunduh sebagai file Excel bertanda.'],
            ['Hasil import', 'Hari yang diisi menjadi jadwal manual, sehingga tidak akan ditimpa lagi oleh generator roster otomatis.'],
            ['Absensi yang sudah lewat', 'Untuk tanggal yang sudah berlalu dan absensinya sudah diproses, status absensi dihitung ulang mengikuti jadwal baru. Jam presensi yang sudah tercatat tetap dipertahankan.'],
            ['', ''],
            [self::CODE_LIST_HEADING, 'Jam kerja'],
        ];

        foreach ($this->shifts as $shift) {
            $rows[] = [
                mb_strtoupper((string) $shift->code),
                sprintf(
                    '%s (%s–%s)%s',
                    $shift->name,
                    substr((string) $shift->start_time, 0, 5),
                    substr((string) $shift->end_time, 0, 5),
                    $shift->crosses_midnight ? ' — lintas tengah malam' : '',
                ),
            ];
        }

        if ($this->shifts->isEmpty()) {
            $rows[] = ['(belum ada)', 'Belum ada shift aktif. Buat shift terlebih dahulu di menu Shift sebelum mengimpor jadwal.'];
        }

        return $rows;
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(2, $sheet->getHighestRow());

                // Locate the code listing rather than hard-coding its row, so adding
                // an instruction above it never leaves the styling behind.
                $codeRow = 1;
                for ($row = 2; $row <= $lastRow; $row++) {
                    if ((string) $sheet->getCell('A'.$row)->getValue() === self::CODE_LIST_HEADING) {
                        $codeRow = $row;

                        break;
                    }
                }

                $sheet->getStyle('A1:B1')->getFont()->setBold(true);

                if ($codeRow > 1) {
                    $sheet->getStyle('A'.$codeRow.':B'.$codeRow)->getFont()->setBold(true);
                }

                $sheet->getColumnDimension('A')->setWidth(26);
                $sheet->getColumnDimension('B')->setWidth(110);
                $sheet->getStyle('B1:B'.$lastRow)->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }
}
