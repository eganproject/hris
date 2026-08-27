<?php

namespace App\Exports;

use App\Imports\ScheduleMatrixImport;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The instructions sheet accompanying the roster template: what a cell may
 * contain, what happens on import, and the shift codes currently available.
 *
 * Everything it documents is derived from ScheduleMatrixImport's constants so the
 * guide cannot drift from what the importer actually accepts — if a token or the
 * WFH suffix ever changes, this sheet changes with it.
 *
 * Laid out as titled sections rather than one long two-column list: the file is
 * read by whoever fills the roster, often once a month, and a flat list of a dozen
 * equally-weighted rows buries the four things they actually need to do.
 */
class ScheduleTemplateGuideSheet implements FromArray, WithEvents, WithTitle
{
    private const SECTION_FILL = 'E8EDF5';

    private const TITLE_FILL = '1F3A5F';

    /** Rows built once so registerEvents() can style the same structure array() emitted. */
    private ?array $rows = null;

    /** @var list<int> 1-indexed rows that are section headers. */
    private array $sectionRows = [];

    /** 1-indexed row where the shift-code listing starts, or 0 when there is none. */
    private int $codeHeaderRow = 0;

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

    /** @return list<list<string>> */
    public function array(): array
    {
        return $this->rows ??= $this->build();
    }

    /**
     * @return list<list<string>>
     */
    private function build(): array
    {
        $rows = [];
        $section = function (string $heading, string $subheading = '') use (&$rows): void {
            $rows[] = ['', ''];
            $this->sectionRows[] = count($rows) + 1;
            $rows[] = [$heading, $subheading];
        };

        $first = $this->shifts->first();
        $firstCode = mb_strtoupper((string) ($first?->code ?? 'P'));
        // Contoh jam diambil dari shift yang benar-benar ada, jadi contoh di petunjuk
        // selalu berupa nilai yang memang akan diterima importer.
        $exampleHours = $first
            ? substr((string) $first->start_time, 0, 5).'-'.substr((string) $first->end_time, 0, 5)
            : '08:00-17:00';
        $wfh = ScheduleMatrixImport::WFH_SUFFIX;
        $offToken = ScheduleMatrixImport::DAY_OFF_TOKENS[0];
        $otherOffTokens = implode(', ', array_map(
            fn (string $token) => '"'.$token.'"',
            array_slice(ScheduleMatrixImport::DAY_OFF_TOKENS, 1),
        ));

        $rows[] = ['PETUNJUK PENGISIAN TEMPLATE JADWAL', $this->month->translatedFormat('F Y')];

        $section('LANGKAH PENGISIAN', 'Kerjakan berurutan');
        $rows[] = ['1', 'Buka sheet "Jadwal". Daftar karyawan sudah terisi sesuai filter yang aktif saat template diunduh.'];
        $rows[] = ['2', 'Isi sel pada kolom tanggal. Aturan pengisiannya ada di bagian "CARA MENGISI SEL" di bawah.'];
        $rows[] = ['3', 'Simpan file ini tanpa mengubah nama sheet, baris 1, maupun baris 2.'];
        $rows[] = ['4', 'Kembali ke menu Jadwal Kerja, klik "Import Excel", lalu unggah file ini.'];

        $section('CARA MENGISI SEL', 'Satu sel = satu karyawan pada satu tanggal');
        $rows[] = ['Kode shift', 'Karyawan masuk pada tanggal itu. Contoh: '.$firstCode.'. Daftar kode lengkap ada di bagian paling bawah.'];
        $rows[] = ['Jam kerja', 'Boleh juga menulis jamnya, contoh '.$exampleHours.'. Boleh ditulis '.$exampleHours.', '.str_replace(':', '.', $exampleHours).', atau tanpa titik dua. Jamnya harus sama persis dengan salah satu shift di daftar paling bawah.'];
        $rows[] = ['Kode/jam + /'.$wfh, 'Masuk, tapi dikerjakan dari rumah. Contoh: '.$firstCode.'/'.$wfh.' atau '.$exampleHours.'/'.$wfh.'. Jam kerjanya tetap mengikuti shift tersebut.'];
        $rows[] = [$offToken, 'Hari libur / tidak dijadwalkan masuk. Bisa juga ditulis '.$otherOffTokens.'.'];
        $rows[] = ['(dikosongkan)', 'Jadwal tanggal itu TIDAK diubah sama sekali. Kosongkan sel yang memang tidak ingin Anda sentuh.'];
        $rows[] = ['Contoh satu baris', 'Tgl 1 = '.$firstCode.'  |  Tgl 2 = '.$exampleHours.'  |  Tgl 3 = '.$firstCode.'/'.$wfh.'  |  Tgl 4 = '.$offToken.'  |  Tgl 5 = dikosongkan (tidak diubah)'];

        $section('YANG TIDAK BOLEH DIUBAH', 'Mengubahnya membuat file gagal dibaca');
        $rows[] = ['Sel B1 (Periode)', 'Menentukan bulan yang diimpor, yaitu '.$this->month->translatedFormat('F Y').'. Mengubahnya berarti menimpa roster bulan lain.'];
        $rows[] = ['Baris 2 (judul)', 'Berisi "'.ScheduleMatrixImport::COLUMN_EMPLOYEE_NUMBER.'", "'.ScheduleMatrixImport::COLUMN_EMPLOYEE_NAME.'", lalu angka 1 sampai '.$this->month->daysInMonth.'.'];
        $rows[] = ['Kolom Nomor Karyawan', 'Dipakai untuk mencocokkan baris dengan data karyawan. Jangan diubah, dihapus, atau diurutkan ulang.'];

        $section('HAL LAIN YANG PERLU DIKETAHUI');
        $rows[] = ['Kolom berwarna merah', 'Hari Sabtu/Minggu dan hari libur nasional. Hanya penanda visual — karyawan tetap boleh dijadwalkan.'];
        $rows[] = ['Baris yang kosong', 'Karyawan bertanda "Ikuti jam kantor" tidak punya baris roster, jadi selnya sengaja dibiarkan kosong.'];
        $rows[] = ['Kalau ada yang salah', 'Seluruh import dibatalkan, tidak ada jadwal yang tersimpan. Daftar kesalahannya bisa diunduh sebagai file bertanda.'];

        $section('SETELAH IMPORT BERHASIL');
        $rows[] = ['Jadwal jadi manual', 'Hari yang Anda isi tidak akan ditimpa lagi oleh generator roster otomatis.'];
        $rows[] = ['Absensi ikut dihitung ulang', 'Untuk tanggal yang sudah lewat dan absensinya sudah diproses, statusnya menyesuaikan jadwal baru. Jam presensi yang sudah tercatat tetap dipertahankan.'];

        $section('KODE SHIFT AKTIF', 'Jam kerja');
        $this->codeHeaderRow = count($rows);

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
            $rows[] = ['(belum ada)', 'Belum ada shift aktif. Buat shift lebih dulu di menu Shift Kerja sebelum mengimpor jadwal.'];
        }

        return $rows;
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->array(); // pastikan struktur & posisi bagiannya sudah dihitung

                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $sheet->getHighestRow());

                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(96);

                // Teks panjang dibungkus dan dirapatkan ke atas supaya tiap baris
                // terbaca sebagai satu blok, bukan satu garis panjang.
                $sheet->getStyle('A1:B'.$lastRow)->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                $sheet->getStyle('A1:A'.$lastRow)->getFont()->setBold(true);

                // Judul halaman.
                $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(13)
                    ->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:B1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TITLE_FILL);
                $sheet->getRowDimension(1)->setRowHeight(24);

                foreach ($this->sectionRows as $row) {
                    $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:B{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::SECTION_FILL);
                    $sheet->getStyle("A{$row}:B{$row}")->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }

                // Kolom kode shift dibaca sebagai kode, bukan kalimat: rata tengah
                // supaya sejajar dengan sel pada sheet "Jadwal".
                if ($this->codeHeaderRow > 0 && $this->codeHeaderRow < $lastRow) {
                    $sheet->getStyle('A'.($this->codeHeaderRow + 1).':A'.$lastRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
            },
        ];
    }
}
