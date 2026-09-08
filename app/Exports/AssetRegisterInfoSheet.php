<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Lembar keterangan: penyaring apa yang dipakai saat berkas ini dibuat, oleh siapa,
 * kapan, dan bagaimana angkanya dibaca. Tanpa ini sebuah register yang sebenarnya
 * hanya berisi satu cabang gampang dikira seluruh aset perusahaan.
 */
class AssetRegisterInfoSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  array<string, string>  $filterLabels
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        private readonly array $filterLabels,
        private readonly array $summary,
        private readonly ?string $generatedBy = null,
    ) {}

    public function title(): string
    {
        return 'Keterangan';
    }

    /** @return array<int, array<int, string>> */
    public function array(): array
    {
        $rows = [
            ['LAPORAN REGISTER ASET', ''],
            ['', ''],
        ];

        foreach ($this->filterLabels as $label => $value) {
            $rows[] = [$label, $value];
        }

        $rows[] = ['', ''];
        $rows[] = ['Jumlah aset', (string) $this->summary['total']];
        $rows[] = ['Nilai perolehan', 'Rp '.number_format((float) $this->summary['value'], 0, ',', '.')];
        $rows[] = ['Dibuat oleh', $this->generatedBy ?? '-'];
        $rows[] = ['Waktu dibuat', now()->translatedFormat('l, d F Y H:i')];
        $rows[] = ['', ''];
        $rows[] = ['CARA MEMBACA', ''];
        $rows[] = ['Cakupan', 'Berkas ini hanya memuat aset yang boleh dilihat oleh akun pembuatnya. Dua orang dengan cakupan berbeda akan menghasilkan angka berbeda dari filter yang sama.'];
        $rows[] = ['Nilai Perolehan', 'Harga beli yang tercatat saat aset didaftarkan, bukan nilai buku. Penyusutan belum dihitung sistem.'];
        $rows[] = ['Pemegang', 'Karyawan yang masa pegangnya masih berjalan. Kosong berarti aset tidak sedang diserahkan ke siapa pun — termasuk barang berstatus Dipakai yang terpakai bersama.'];
        $rows[] = ['Lokasi Pemilik vs Sekarang', 'Lokasi Pemilik tidak berubah saat barang dipindah; Lokasi Sekarang mengikuti perpindahan.'];
        $rows[] = ['Filter lokasi', 'Menyaring lokasi pemilik ATAU lokasi sekarang, supaya aset yang dititipkan ke cabang lain tetap muncul di kedua sisi.'];

        return $rows;
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = count($this->array());

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A1:A{$last}")->getFont()->setBold(true);
                $sheet->getColumnDimension('B')->setWidth(95);
                $sheet->getStyle("B1:B{$last}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}
