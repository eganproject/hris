<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Tampilan seragam untuk sheet bertabel: baris judul kolom yang tegas, dibekukan
 * agar tetap terlihat saat digulir, dan filter otomatis supaya penerima file bisa
 * langsung menyaring/menyortir tanpa menyiapkan apa pun.
 */
trait FormatsSheet
{
    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->freezePane('A2');

                // Tanpa baris data, autofilter hanya menghasilkan panah yang menyesatkan.
                if ($lastRow > 1) {
                    $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
                }
            },
        ];
    }
}
