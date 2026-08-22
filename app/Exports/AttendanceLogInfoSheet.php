<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Lembar keterangan: filter apa yang dipakai saat file ini dibuat, oleh siapa, kapan,
 * dan bagaimana angka-angkanya didefinisikan. Tanpa ini, berkas ekspor yang beredar
 * lewat email kehilangan konteksnya dan gampang dibaca keliru.
 */
class AttendanceLogInfoSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly array $meta,
        private readonly int $rowCount,
        private readonly int $employeeCount,
    ) {}

    public function title(): string
    {
        return 'Keterangan';
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $worked = collect(AttendanceStatus::WORKED)
            ->map(fn (AttendanceStatus $status) => $status->label())
            ->implode(', ');

        $statusLabel = $this->meta['status'] ?? null;

        return [
            ['LAPORAN LOG ABSENSI', ''],
            ['', ''],
            ['Periode', (string) ($this->meta['periode'] ?? '-')],
            ['Lokasi', (string) ($this->meta['lokasi'] ?? 'Semua lokasi')],
            ['Divisi', (string) ($this->meta['divisi'] ?? 'Semua divisi')],
            ['Filter status', $statusLabel ? (AttendanceStatus::tryFrom($statusLabel)?->label() ?? $statusLabel) : 'Semua status'],
            ['Kata kunci pencarian', (string) ($this->meta['pencarian'] ?? '-')],
            ['', ''],
            ['Jumlah baris', (string) $this->rowCount],
            ['Jumlah karyawan', (string) $this->employeeCount],
            ['Dibuat oleh', (string) ($this->meta['dibuat_oleh'] ?? '-')],
            ['Waktu dibuat', now()->translatedFormat('l, d F Y H:i')],
            ['', ''],
            ['CARA MEMBACA', ''],
            ['Hadir', 'Menghitung status: '.$worked.'. Orangnya bekerja hari itu, baik dari kantor, rumah, maupun luar kota.'],
            ['Hari Kerja', 'Hari tercatat dikurangi Libur Nasional dan Libur jadwal, supaya persen kehadiran tidak terdilusi akhir pekan.'],
            ['% Kehadiran', 'Hadir dibagi Hari Kerja, dalam persen.'],
            ['Rata-rata Telat', 'Dihitung hanya atas hari yang benar-benar terlambat, bukan dibagi seluruh hari kerja.'],
            ['Jam Kerja & Lembur', 'Dalam satuan jam desimal (mis. 8,5 = 8 jam 30 menit) agar bisa langsung dijumlahkan.'],
            ['Lembur', 'Angka hasil perhitungan shift, bukan lembur yang sudah disetujui. Untuk penggajian, pakai rekap Lembur.'],
            ['Jam kosong', 'Berarti tidak ada punch pada hari itu, misalnya Alfa, Cuti, atau Libur.'],
            ['Info Shift', 'Jam kerja tiap shift yang muncul di data ini. Kolom Terlambat dan Jam Kerja hanya bisa dibaca dengan benar bersama lembar itu.'],
        ];
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A14')->getFont()->setBold(true);
                $sheet->getStyle('A1:A22')->getFont()->setBold(true);
                $sheet->getColumnDimension('B')->setWidth(95);
                $sheet->getStyle('B1:B22')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
