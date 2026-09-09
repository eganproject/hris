<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas Excel register aset: empat lembar yang menjawab pertanyaan berbeda —
 * rekap per sumbu untuk rapat, ringkasan per merek dan model untuk melihat barang apa
 * yang menumpuk, daftar lengkap untuk penelusuran dan pivot sendiri, dan lembar
 * keterangan supaya berkas yang beredar lewat surel tidak kehilangan konteks
 * filternya.
 *
 * "Ringkas per Merek" dan "Register Aset" sengaja berdampingan, bukan salah satunya
 * saja. Yang tercollapse enak dibaca tapi tidak bisa diolah lagi; yang datar bisa
 * difilter dan dipivot sendiri tapi panjang. Menghapus salah satunya berarti
 * memaksa separuh pembaca bekerja dengan bentuk yang salah untuk keperluannya.
 *
 * Rekap diletakkan lebih dulu karena itu yang paling sering dibuka.
 */
class AssetRegisterExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, Asset>  $assets  sudah tercakup, tersaring, dan terurut
     * @param  Collection<int, array<string, mixed>>  $groups
     * @param  Collection<int, array<string, mixed>>  $names  baris merek + model
     * @param  array<string, mixed>  $summary
     * @param  array<string, string>  $filterLabels
     */
    public function __construct(
        private readonly Collection $assets,
        private readonly Collection $groups,
        private readonly Collection $names,
        private readonly array $summary,
        private readonly array $filterLabels,
        private readonly string $groupLabel,
        private readonly ?string $generatedBy = null,
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            new AssetRegisterSummarySheet($this->groups, $this->summary, $this->groupLabel),
            new AssetRegisterBrandSheet($this->names, $this->summary),
            new AssetRegisterListSheet($this->assets),
            new AssetRegisterInfoSheet($this->filterLabels, $this->summary, $this->generatedBy),
        ];
    }
}
