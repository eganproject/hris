<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Versi tercollapse dari register: satu baris per nama aset, bukan per unit.
 *
 * Pasangannya adalah lembar "Register Aset" yang tetap datar satu baris per unit.
 * Keduanya sengaja ada dan tidak saling menggantikan — lembar ini untuk membaca
 * cepat ("iPhone XR ada berapa?"), lembar datar untuk menelusuri unit tertentu dan
 * untuk diolah sendiri dengan filter atau pivot.
 *
 * Kolom "Variasi Ejaan" bukan hiasan: ia menunjuk nama yang ditulis lebih dari satu
 * cara di formulir. Angka di atas 1 berarti ada pekerjaan merapikan data, dan tanpa
 * kolom ini penggabungan justru menyembunyikannya.
 */
class AssetRegisterNameSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $names
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        private readonly Collection $names,
        private readonly array $summary,
    ) {}

    public function title(): string
    {
        return 'Ringkas per Nama';
    }

    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $rows = [
            ['Nama Aset', 'Jumlah Unit', 'Nilai Perolehan', 'Variasi Ejaan'],
        ];

        foreach ($this->names as $name) {
            $rows[] = [
                $name['name'],
                $name['units'],
                $name['value'],
                // 1 ditulis sebagai teks kosong supaya mata langsung jatuh ke baris
                // yang bermasalah, bukan ke kolom penuh angka 1 yang tidak berarti apa-apa.
                $name['spellings'] > 1 ? $name['spellings'].' ejaan berbeda' : '',
            ];
        }

        $rows[] = ['TOTAL', (int) $this->summary['total'], (float) $this->summary['value'], ''];

        return $rows;
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = $this->names->count() + 2;

                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                $sheet->getStyle("A{$last}:D{$last}")->getFont()->setBold(true);
                $sheet->getStyle("C2:C{$last}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->freezePane('A2');
            },
        ];
    }
}
