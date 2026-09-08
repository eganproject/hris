<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Rekap jumlah dan nilai aset per sumbu yang dipilih, plus barisan totalnya. */
class AssetRegisterSummarySheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        private readonly Collection $groups,
        private readonly array $summary,
        private readonly string $groupLabel,
    ) {}

    public function title(): string
    {
        return 'Rekap';
    }

    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $total = max(1, (int) $this->summary['total']);

        $rows = [
            [$this->groupLabel, 'Jumlah Aset', 'Nilai Perolehan', '% Jumlah'],
        ];

        foreach ($this->groups as $group) {
            $rows[] = [
                $group['label'],
                $group['count'],
                $group['value'],
                round($group['count'] / $total * 100, 1),
            ];
        }

        $rows[] = ['TOTAL', (int) $this->summary['total'], (float) $this->summary['value'], 100];

        return $rows;
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = $this->groups->count() + 2;

                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                $sheet->getStyle("A{$last}:D{$last}")->getFont()->setBold(true);
                $sheet->getStyle("C2:C{$last}")->getNumberFormat()->setFormatCode('#,##0');
            },
        ];
    }
}
