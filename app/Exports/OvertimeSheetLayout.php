<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tata letak bersama lembar tabel lembur: judul + subjudul di atas, baris judul
 * kolom di baris 4 (dibekukan & diberi filter), data, lalu baris TOTAL yang
 * memakai SUBTOTAL sehingga angkanya ikut berubah saat tabel disaring.
 */
trait OvertimeSheetLayout
{
    /** Baris tempat judul kolom berada; data mulai tepat di bawahnya. */
    private const HEADER_ROW = 4;

    /** Format durasi Excel: jam bisa lebih dari 24, mis. 37:30. */
    private const DURATION_FORMAT = '[h]:mm';

    private const STATUS_FILLS = [
        'Disetujui' => 'DCFCE7',
        'Menunggu' => 'FEF3C7',
        'Ditolak' => 'FEE2E2',
    ];

    /** Menit → nilai waktu Excel (pecahan hari), agar bisa dijumlah dan tampil sebagai jam:menit. */
    private static function duration(?int $minutes): ?float
    {
        return $minutes === null ? null : $minutes / 1440;
    }

    private static function hours(?int $minutes): ?float
    {
        return $minutes === null ? null : round($minutes / 60, 2);
    }

    private static function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /** Teks subjudul: periode dan filter aktif. */
    private function subtitle(): string
    {
        $parts = ['Periode: '.($this->meta['periode'] ?? '-')];

        foreach (['status' => 'Status', 'lokasi' => 'Lokasi', 'divisi' => 'Divisi', 'pencarian' => 'Pencarian'] as $key => $label) {
            if (! empty($this->meta[$key])) {
                $parts[] = $label.': '.$this->meta[$key];
            }
        }

        return implode('   ·   ', $parts);
    }

    /**
     * @param  array<int, float|int>  $widths  lebar per kolom (indeks mulai 1)
     * @param  list<int>  $centered  kolom yang rata tengah
     * @param  list<int>  $wrapped  kolom teks panjang yang dibungkus
     */
    private function styleTable(
        Worksheet $sheet,
        int $columnCount,
        int $dataRows,
        array $widths,
        array $centered = [],
        array $wrapped = [],
        ?int $statusColumn = null,
    ): void {
        $last = self::col($columnCount);
        $header = self::HEADER_ROW;
        $firstData = $header + 1;
        $lastData = $header + $dataRows;
        $total = $lastData + 1;

        // Judul & subjudul.
        $sheet->mergeCells("A1:{$last}1");
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('111827');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('4B5563');
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Judul kolom.
        $sheet->getStyle("A{$header}:{$last}{$header}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($header)->setRowHeight(45);

        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(self::col($index))->setWidth($width);
        }

        // Garis tipis di seluruh tabel, termasuk baris total.
        $sheet->getStyle("A{$header}:{$last}{$total}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        $sheet->getStyle("A{$firstData}:{$last}{$total}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        if ($dataRows > 0) {
            foreach ($centered as $index) {
                $sheet->getStyle(self::col($index)."{$firstData}:".self::col($index).$lastData)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach ($wrapped as $index) {
                $sheet->getStyle(self::col($index)."{$firstData}:".self::col($index).$lastData)
                    ->getAlignment()->setWrapText(true);
            }

            // Baris belang tipis supaya mata tidak loncat baris pada tabel lebar.
            for ($row = $firstData + 1; $row <= $lastData; $row += 2) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
            }

            if ($statusColumn !== null) {
                $col = self::col($statusColumn);
                for ($row = $firstData; $row <= $lastData; $row++) {
                    $fill = self::STATUS_FILLS[(string) $sheet->getCell("{$col}{$row}")->getValue()] ?? null;
                    if ($fill) {
                        $sheet->getStyle("{$col}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
                    }
                }
            }

            // Filter hanya atas judul + data, tidak ikut baris total.
            $sheet->setAutoFilter("A{$header}:{$last}{$lastData}");
        }

        // Baris total.
        $sheet->getStyle("A{$total}:{$last}{$total}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '374151']]],
        ]);

        $sheet->freezePane('A'.$firstData);

        // Siap cetak: A4 lanskap, pas satu lebar halaman, judul kolom berulang.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($header, $header);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);
        $sheet->getHeaderFooter()->setOddFooter('&L'.($this->meta['periode'] ?? '').'&RHalaman &P dari &N');
    }

    /** Rumus total yang mengikuti filter (fungsi 109 = SUM yang melewati baris tersembunyi). */
    private static function subtotal(int $column, int $dataRows): string|int
    {
        $col = self::col($column);
        $first = self::HEADER_ROW + 1;
        $last = self::HEADER_ROW + max($dataRows, 1);

        return $dataRows > 0 ? "=SUBTOTAL(109,{$col}{$first}:{$col}{$last})" : 0;
    }

    private static function formatColumn(Worksheet $sheet, int $column, int $fromRow, int $toRow, string $format): void
    {
        $col = self::col($column);
        $sheet->getStyle("{$col}{$fromRow}:{$col}{$toRow}")->getNumberFormat()->setFormatCode($format);
    }
}
