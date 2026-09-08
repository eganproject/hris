<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Daftar aset satu per satu.
 *
 * Berbeda dari AssetsExport yang kolomnya sengaja dibuat bisa diimpor kembali,
 * lembar ini laporan: ia membawa pemegang aset dan tanggal serah terima, yang justru
 * tidak boleh ada di berkas impor karena kepemilikan hanya lahir dari serah terima.
 */
class AssetRegisterListSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param  Collection<int, Asset>  $assets */
    public function __construct(private readonly Collection $assets) {}

    public function title(): string
    {
        return 'Register Aset';
    }

    /** @return Collection<int, Asset> */
    public function collection(): Collection
    {
        return $this->assets;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Kode Aset', 'Nama Aset', 'Kategori', 'Merek', 'Model', 'Nomor Seri',
            'Lokasi Pemilik', 'Lokasi Sekarang', 'Divisi Pemilik',
            'Status', 'Kondisi', 'Pemegang', 'No. Karyawan', 'Sejak',
            'Tanggal Perolehan', 'Nilai Perolehan', 'Garansi Berakhir',
        ];
    }

    /**
     * @param  Asset  $asset
     * @return list<string|float|null>
     */
    public function map($asset): array
    {
        $assignment = $asset->currentAssignment;

        return [
            $asset->asset_code,
            $asset->name,
            $asset->category?->name,
            $asset->brand,
            $asset->model,
            $asset->serial_number,
            $asset->owningBranch?->name,
            $asset->currentBranch?->name,
            $asset->department?->name,
            $asset->status_label,
            $asset->condition_label,
            $assignment?->employee?->full_name,
            $assignment?->employee?->employee_number,
            $assignment?->assigned_at?->format('Y-m-d'),
            $asset->acquired_at?->format('Y-m-d'),
            $asset->acquisition_cost === null ? null : (float) $asset->acquisition_cost,
            $asset->warranty_expires_at?->format('Y-m-d'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
