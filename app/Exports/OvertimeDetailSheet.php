<?php

namespace App\Exports;

use App\Models\OvertimeApproval;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Rincian tiap pengajuan lembur, urut tanggal. Tanggal & durasi disimpan sebagai
 * nilai Excel asli (bukan teks), jadi bisa disortir, disaring, dan dijumlahkan.
 */
class OvertimeDetailSheet implements FromArray, WithEvents, WithTitle
{
    use OvertimeSheetLayout;

    private const HEADINGS = [
        'No', 'Tanggal', 'Hari', 'NIK', 'Nama Karyawan', 'Divisi', 'Lokasi',
        'Jam Mulai', 'Jam Selesai', 'Diajukan (jam:menit)', 'Hitungan Mesin (jam:menit)',
        'Disetujui (jam:menit)', 'Disetujui (jam desimal)', 'Status',
        'Alasan Lembur', 'Atasan', 'Diputuskan Pada', 'Catatan Atasan',
    ];

    private const STATUS_COLUMN = 14;

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
        return 'Rincian Lembur';
    }

    /** @return list<array<int, mixed>> */
    public function array(): array
    {
        $body = $this->rows->values()->map(function (OvertimeApproval $row, int $i) {
            $employee = $row->employee;
            $approved = $row->status === OvertimeApproval::STATUS_APPROVED;

            return [
                $i + 1,
                ExcelDate::PHPToExcel($row->work_date->copy()->startOfDay()),
                $row->work_date->translatedFormat('l'),
                $employee?->employee_number ?: '-',
                $employee?->full_name ?? '-',
                $employee?->departments->pluck('name')->implode(', ') ?: ($employee?->department?->name ?? '-'),
                $employee?->branch?->name ?? '-',
                $row->start_time ? substr($row->start_time, 0, 5) : '-',
                $row->end_time ? substr($row->end_time, 0, 5) : '-',
                self::duration((int) $row->requested_minutes),
                self::duration($row->computed_minutes),
                $approved ? self::duration((int) $row->approved_minutes) : null,
                $approved ? self::hours((int) $row->approved_minutes) : null,
                $row->status_label,
                $row->reason ?? '',
                $row->supervisor?->full_name ?? '-',
                $row->decided_at ? ExcelDate::PHPToExcel($row->decided_at) : null,
                $row->notes ?? '',
            ];
        })->all();

        $count = count($body);

        return [
            ['RINCIAN PENGAJUAN LEMBUR — '.mb_strtoupper((string) ($this->meta['periode'] ?? ''))],
            [$this->subtitle()],
            [''],
            self::HEADINGS,
            ...$body,
            ['TOTAL', '', '', '', $count.' pengajuan', '', '', '', '',
                self::subtotal(10, $count), self::subtotal(11, $count),
                self::subtotal(12, $count), self::subtotal(13, $count),
                '', '', '', '', ''],
        ];
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rows = $this->rows->count();
                $from = self::HEADER_ROW + 1;
                $to = self::HEADER_ROW + $rows + 1;

                $this->styleTable(
                    $sheet,
                    count(self::HEADINGS),
                    $rows,
                    [1 => 5, 2 => 12, 3 => 10, 4 => 20, 5 => 26, 6 => 18, 7 => 16, 8 => 9, 9 => 9,
                        10 => 12, 11 => 13, 12 => 12, 13 => 12, 14 => 12, 15 => 40, 16 => 22, 17 => 17, 18 => 32],
                    centered: [1, 2, 3, 8, 9, 14, 17],
                    wrapped: [15, 18],
                    statusColumn: self::STATUS_COLUMN,
                );

                self::formatColumn($sheet, 2, $from, $to - 1, 'dd/mm/yyyy');
                foreach ([10, 11, 12] as $column) {
                    self::formatColumn($sheet, $column, $from, $to, self::DURATION_FORMAT);
                }
                self::formatColumn($sheet, 13, $from, $to, '#,##0.00');
                self::formatColumn($sheet, 17, $from, $to - 1, 'dd/mm/yyyy hh:mm');

                $sheet->getStyle('L'.self::HEADER_ROW.':M'.self::HEADER_ROW)->getFill()->getStartColor()->setRGB('065F46');
            },
        ];
    }
}
