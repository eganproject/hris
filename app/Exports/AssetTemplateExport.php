<?php

namespace App\Exports;

use App\Imports\AssetsImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template impor aset yang masih kosong: satu lembar "Data Aset" berisi baris judul
 * saja, plus lembar "Petunjuk Pengisian" yang menerangkan tiap kolom, wajib atau
 * tidaknya, dan contoh isinya. Kolomnya diambil dari AssetsImport, jadi templatenya
 * tidak pernah bergeser dari apa yang sebenarnya diterima importer.
 */
class AssetTemplateExport implements WithMultipleSheets
{
    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            $this->dataSheet(),
            $this->guideSheet(),
        ];
    }

    private function dataSheet(): object
    {
        return new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
        {
            public function array(): array
            {
                return []; // hanya judulnya: tidak ada baris contoh yang harus dihapus dulu
            }

            /** @return list<string> */
            public function headings(): array
            {
                return array_map(fn ($column) => $column['header'], AssetsImport::columns());
            }

            public function title(): string
            {
                return 'Data Aset';
            }

            /** @return array<int, array<string, mixed>> */
            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }

    private function guideSheet(): object
    {
        return new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
        {
            /** @return list<list<string>> */
            public function array(): array
            {
                return array_map(fn ($column) => [
                    $column['header'],
                    $column['required'] ? 'Wajib' : 'Opsional',
                    $column['desc'],
                    $column['example'],
                ], AssetsImport::columns());
            }

            /** @return list<string> */
            public function headings(): array
            {
                return ['Kolom', 'Wajib / Opsional', 'Keterangan', 'Contoh Isi'];
            }

            public function title(): string
            {
                return 'Petunjuk Pengisian';
            }

            /** @return array<int, array<string, mixed>> */
            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }
}
