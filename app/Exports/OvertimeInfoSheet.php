<?php

namespace App\Exports;

use App\Models\OvertimeApproval;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Lembar keterangan: filter yang dipakai, ringkasan angka, dan cara membaca kolom.
 */
class OvertimeInfoSheet implements FromArray, WithEvents, WithTitle
{
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
        return 'Keterangan';
    }

    /** @return list<array<int, string>> */
    public function array(): array
    {
        $approvedMinutes = (int) $this->rows->where('status', OvertimeApproval::STATUS_APPROVED)->sum('approved_minutes');

        return [
            ['LAPORAN LEMBUR', ''],
            ['', ''],
            ['FILTER', ''],
            ['Periode', (string) ($this->meta['periode'] ?? '-')],
            ['Status', (string) ($this->meta['status'] ?? 'Semua status')],
            ['Lokasi', (string) ($this->meta['lokasi'] ?? 'Semua lokasi')],
            ['Divisi', (string) ($this->meta['divisi'] ?? 'Semua divisi')],
            ['Kata kunci pencarian', (string) ($this->meta['pencarian'] ?? '-')],
            ['', ''],
            ['RINGKASAN', ''],
            ['Jumlah karyawan', (string) $this->rows->pluck('employee_id')->unique()->count()],
            ['Jumlah pengajuan', (string) $this->rows->count()],
            ['Disetujui', (string) $this->rows->where('status', OvertimeApproval::STATUS_APPROVED)->count()],
            ['Menunggu atasan', (string) $this->rows->where('status', OvertimeApproval::STATUS_PENDING)->count()],
            ['Ditolak', (string) $this->rows->where('status', OvertimeApproval::STATUS_REJECTED)->count()],
            ['Total lembur disetujui', intdiv($approvedMinutes, 60).' jam '.($approvedMinutes % 60).' menit ('.number_format($approvedMinutes / 60, 2, ',', '.').' jam)'],
            ['', ''],
            ['Dibuat oleh', (string) ($this->meta['dibuat_oleh'] ?? '-')],
            ['Waktu dibuat', now()->translatedFormat('l, d F Y H:i')],
            ['', ''],
            ['CARA MEMBACA', ''],
            ['Rekap per Karyawan', 'Satu baris per karyawan. Kolom "Total Disetujui" (hijau) adalah angka yang dipakai untuk penggajian.'],
            ['Rincian Lembur', 'Satu baris per pengajuan, urut tanggal. Bisa disaring lewat panah di judul kolom.'],
            ['Baris TOTAL', 'Memakai SUBTOTAL, jadi ikut berubah mengikuti filter yang sedang aktif.'],
            ['jam:menit', 'Durasi dalam format jam:menit (mis. 1:30 = 1 jam 30 menit). Bisa dijumlahkan langsung.'],
            ['jam desimal', 'Durasi yang sama dalam angka desimal (mis. 1,50 = 1 jam 30 menit), siap dikalikan tarif lembur.'],
            ['Diajukan', 'Jam lembur yang diisi karyawan (Jam Mulai sampai Jam Selesai).'],
            ['Hitungan Mesin', 'Lembur menurut absensi mesin pada hari itu, sebagai pembanding. Kosong bila tidak ada data absensi.'],
            ['Disetujui', 'Jam yang disetujui atasan. Kosong bila pengajuan masih menunggu atau ditolak.'],
        ];
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                foreach (['A3', 'A10', 'A21'] as $cell) {
                    $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('065F46');
                }
                $sheet->getStyle('A4:A29')->getFont()->setBold(true);
                $sheet->getColumnDimension('A')->setWidth(26);
                $sheet->getColumnDimension('B')->setWidth(95);
                $sheet->getStyle('B1:B29')->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }
}
