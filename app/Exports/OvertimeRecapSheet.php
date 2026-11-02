<?php

namespace App\Exports;

use App\Models\OvertimeApproval;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Lembar pertama: satu baris per karyawan. Kolom "Total Disetujui" inilah angka
 * yang dipakai penggajian; jumlah pengajuan per status membantu melihat siapa yang
 * masih menunggu keputusan atasan.
 */
class OvertimeRecapSheet implements FromArray, WithEvents, WithTitle
{
    use OvertimeSheetLayout;

    private const HEADINGS = [
        'No', 'NIK', 'Nama Karyawan', 'Divisi', 'Lokasi', 'Jabatan', 'Atasan',
        'Jumlah Pengajuan', 'Disetujui', 'Menunggu', 'Ditolak',
        'Total Diajukan (jam:menit)', 'Total Disetujui (jam:menit)', 'Total Disetujui (jam desimal)',
    ];

    /** @var list<array<int, mixed>>|null */
    private ?array $body = null;

    /**
     * @param  Collection<int, OvertimeApproval>  $rows
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $meta = [],
    ) {}

    public function title(): string
    {
        return 'Rekap per Karyawan';
    }

    /** @return list<array<int, mixed>> */
    private function body(): array
    {
        return $this->body ??= $this->rows
            ->groupBy('employee_id')
            ->map(function (Collection $items) {
                $employee = $items->first()->employee;
                $approved = $items->where('status', OvertimeApproval::STATUS_APPROVED);

                return [
                    'nama' => (string) ($employee?->full_name ?? '-'),
                    'cells' => [
                        $employee?->employee_number ?: '-',
                        $employee?->full_name ?? '-',
                        $employee?->departments->pluck('name')->implode(', ') ?: ($employee?->department?->name ?? '-'),
                        $employee?->branch?->name ?? '-',
                        $employee?->jobPosition?->name ?? '-',
                        $items->first()->supervisor?->full_name ?? '-',
                        $items->count(),
                        $approved->count(),
                        $items->where('status', OvertimeApproval::STATUS_PENDING)->count(),
                        $items->where('status', OvertimeApproval::STATUS_REJECTED)->count(),
                        self::duration((int) $items->sum('requested_minutes')),
                        self::duration((int) $approved->sum('approved_minutes')),
                        self::hours((int) $approved->sum('approved_minutes')),
                    ],
                ];
            })
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (array $row, int $i) => [$i + 1, ...$row['cells']])
            ->all();
    }

    /** @return list<array<int, mixed>> */
    public function array(): array
    {
        $body = $this->body();
        $count = count($body);

        $total = ['TOTAL', '', $count.' karyawan', '', '', '', ''];
        foreach (range(8, 14) as $column) {
            $total[] = self::subtotal($column, $count);
        }

        return [
            ['REKAP LEMBUR PER KARYAWAN — '.mb_strtoupper((string) ($this->meta['periode'] ?? ''))],
            [$this->subtitle()],
            [''],
            self::HEADINGS,
            ...$body,
            $total,
        ];
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rows = count($this->body());
                $from = self::HEADER_ROW + 1;
                $to = self::HEADER_ROW + $rows + 1;

                $this->styleTable(
                    $sheet,
                    count(self::HEADINGS),
                    $rows,
                    [1 => 5, 2 => 20, 3 => 28, 4 => 20, 5 => 16, 6 => 20, 7 => 24, 8 => 12, 9 => 11, 10 => 11, 11 => 10, 12 => 14, 13 => 15, 14 => 15],
                    centered: [1, 8, 9, 10, 11],
                );

                foreach ([8, 9, 10, 11] as $column) {
                    self::formatColumn($sheet, $column, $from, $to, '#,##0');
                }
                self::formatColumn($sheet, 12, $from, $to, self::DURATION_FORMAT);
                self::formatColumn($sheet, 13, $from, $to, self::DURATION_FORMAT);
                self::formatColumn($sheet, 14, $from, $to, '#,##0.00');

                // Kolom angka penggajian ditonjolkan.
                $sheet->getStyle('M'.self::HEADER_ROW.':N'.self::HEADER_ROW)->getFill()->getStartColor()->setRGB('065F46');
            },
        ];
    }
}
