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
        // Dua baris, bukan satu "sedang terpakai": angka yang digabung menghapus beda
        // antara barang yang bisa ditagih ke seseorang dan barang yang tidak.
        $rows[] = ['Dipegang karyawan', (string) $this->summary['assigned']];
        $rows[] = ['Dipakai bersama', (string) $this->summary['in_use']];
        $rows[] = ['Dibuat oleh', $this->generatedBy ?? '-'];
        $rows[] = ['Waktu dibuat', now()->translatedFormat('l, d F Y H:i')];
        $rows[] = ['', ''];
        $rows[] = ['CARA MEMBACA', ''];
        $rows[] = ['Cakupan', 'Berkas ini hanya memuat aset yang boleh dilihat oleh akun pembuatnya. Dua orang dengan cakupan berbeda akan menghasilkan angka berbeda dari filter yang sama.'];
        $rows[] = ['Spesifikasi', 'Keterangan teknis yang diisi di master aset. Di lembar ini ditulis utuh; di layar dan cetakan PDF ia dipotong agar tabelnya tetap terbaca.'];
        $rows[] = ['Nilai perolehan', 'Sengaja tidak ada di berkas ini. Register aset menjawab barang apa, di mana, dipegang siapa, dan spesifikasinya apa — bukan berapa nilainya. Angkanya tetap tersimpan dan bisa dilihat di halaman detail tiap aset atau diekspor dari halaman Daftar Aset.'];
        $rows[] = ['Pemegang', 'Karyawan yang masa pegangnya masih berjalan. Kosong berarti aset tidak sedang diserahkan ke siapa pun.'];
        $rows[] = ['Dipegang vs Dipakai', 'Dua status yang berbeda dan tidak boleh dijumlahkan. "Dipegang" lahir dari serah terima, jadi ada satu karyawan yang bisa dimintai pertanggungjawaban dan kolom Pemegang terisi. "Dipakai" adalah barang pakai bersama yang menetap di satu ruangan — memang terpakai, tapi kolom Pemegangnya sengaja kosong karena tidak ada yang menandatangani.'];
        $rows[] = ['Ringkas per Nama vs Register Aset', 'Dua bentuk dari data yang sama, sengaja dua-duanya ada. "Ringkas per Nama" menggabungkan aset yang namanya sama menjadi satu baris — untuk membaca cepat barang apa yang menumpuk. "Register Aset" tetap satu baris per unit — untuk menelusuri unit tertentu dan untuk diolah sendiri dengan filter atau pivot. Totalnya selalu sama.'];
        $rows[] = ['Penggabungan nama', 'Dua aset digabung bila namanya sama setelah besar-kecil huruf dan spasi tepinya diabaikan, jadi "iPhone XR", "IPHONE XR", dan "iPhone XR " terhitung satu. Penulisan yang benar-benar berbeda seperti "iPhone XR" dan "iPhone XR 64GB" tetap terpisah; kolom Variasi Ejaan menandai nama yang perlu dirapikan di master aset.'];
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
