<?php

namespace App\Exports;

use App\Models\OvertimeApproval;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

/**
 * Berkas Excel Pemantauan Lembur. Tiga lembar, dari yang paling sering dibuka:
 * rekap per karyawan (untuk penggajian), rincian tiap pengajuan (untuk
 * penelusuran), dan keterangan filter + cara membaca supaya berkas yang beredar
 * lewat email tidak kehilangan konteks.
 *
 * Isinya sama persis dengan yang tampil di layar (bulan, status, lokasi, divisi,
 * pencarian, dan cakupan tim), hanya tanpa paginasi.
 *
 * Rumus TOTAL dihitung saat file dibuat, supaya angkanya langsung tampil juga di
 * penampil yang tidak menghitung ulang (pratinjau email, Google Drive, dsb.).
 */
class OvertimeExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    /**
     * @param  Collection<int, OvertimeApproval>  $rows  sudah difilter, urut tanggal lalu nama
     * @param  array<string, mixed>  $meta  filter yang dipakai, untuk judul & lembar keterangan
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $meta = [],
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            new OvertimeRecapSheet($this->rows, $this->meta),
            new OvertimeDetailSheet($this->rows, $this->meta),
            new OvertimeInfoSheet($this->rows, $this->meta),
        ];
    }
}
