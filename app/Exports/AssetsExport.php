<?php

namespace App\Exports;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor daftar aset ke .xlsx.
 *
 * Query-nya dibangun dari cakupan dan penyaring yang sama persis dengan halaman
 * daftar (Asset::scopeVisibleTo + scopeMatchingFilters), bukan salinannya — berkas
 * yang diunduh tidak boleh berisi satu baris pun di luar yang tampil di layar.
 */
class AssetsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private array $filters = [], private ?User $user = null) {}

    public function title(): string
    {
        return 'Daftar Aset';
    }

    /** @return Builder<Asset> */
    public function query(): Builder
    {
        return Asset::query()
            ->with(['category:id,name', 'owningBranch:id,name', 'currentBranch:id,name', 'department:id,name', 'departments:id,name', 'storageLocation:id,full_path'])
            ->when($this->user, fn ($query) => $query->visibleTo($this->user))
            ->matchingFilters($this->filters)
            ->orderBy('asset_code');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Kode Aset',
            'Nama',
            'Kategori',
            'Merek',
            'Model',
            'Nomor Seri',
            'Status',
            'Kondisi',
            'Lokasi Pemilik',
            'Lokasi Sekarang',
            'Tempat Penyimpanan',
            'Divisi',
            'Tanggal Perolehan',
            'Nilai Perolehan',
            'Garansi Berakhir',
            'Catatan',
        ];
    }

    /**
     * @param  Asset  $asset
     * @return list<string|null>
     */
    public function map($asset): array
    {
        return [
            $asset->asset_code,
            $asset->name,
            $asset->category?->name,
            $asset->brand,
            $asset->model,
            $asset->serial_number,
            $asset->status_label,
            $asset->condition_label,
            $asset->owningBranch?->name,
            $asset->currentBranch?->name,
            $asset->storageLocation?->full_path,
            // Aset milik bersama tetap satu baris: kedua divisinya ditulis
            // berdampingan, karena memecahnya jadi dua baris akan membuat siapa pun
            // yang menjumlahkan nilai perolehan menghitungnya dua kali.
            $asset->departments->pluck('name')->implode(', ') ?: $asset->department?->name,
            $asset->acquired_at?->format('d/m/Y'),
            $asset->acquisition_cost,
            $asset->warranty_expires_at?->format('d/m/Y'),
            $asset->notes,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
