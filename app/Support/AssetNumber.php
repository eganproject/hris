<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;

/**
 * Kode aset, dibangkitkan sistem dan tidak pernah diketik tangan:
 *
 *     AST-[prefix kategori]-[kode lokasi pemilik]-[id aset 4 digit]
 *     e.g. kategori "LPT", cabang pemilik "HO", id 12  =>  AST-LPT-HO-0012
 *
 * Karena id ikut di dalamnya, kode baru bisa dibentuk setelah barisnya ada —
 * Asset menuliskannya tepat setelah insert (lihat Asset::booted()), pola yang sama
 * dengan EmployeeNumber.
 *
 * Bedanya dengan nomor karyawan: kode aset TIDAK pernah diperbarui. Nomor karyawan
 * ikut berubah saat orangnya pindah lokasi, sedangkan kode aset adalah identitas
 * permanen — memindahkan barang ke cabang lain tidak boleh mengubah label yang sudah
 * tertempel di fisiknya. Karena itu yang dipakai adalah cabang PEMILIK, yang memang
 * tidak ikut berubah saat barangnya berpindah tempat.
 */
class AssetNumber
{
    public const PREFIX = 'AST';

    /** Pengganti bila kategori atau lokasi tidak punya kode yang terbaca. */
    public const FALLBACK = 'XX';

    public static function for(Asset $asset): string
    {
        // Dibaca segar dari tabelnya: relasi yang sudah termuat bisa jadi masih
        // menunjuk nilai sebelum penyimpanan.
        $categoryPrefix = $asset->category_id
            ? AssetCategory::query()->whereKey($asset->category_id)->value('asset_prefix')
            : null;

        $branchCode = $asset->owning_branch_id
            ? Branch::query()->whereKey($asset->owning_branch_id)->value('code')
            : null;

        return self::format($categoryPrefix, $branchCode, $asset->id);
    }

    public static function format(?string $categoryPrefix, ?string $branchCode, int $assetId): string
    {
        return sprintf(
            '%s-%s-%s-%04d',
            self::PREFIX,
            self::segment($categoryPrefix),
            self::segment($branchCode),
            $assetId,
        );
    }

    /**
     * Kode kategori dan kode cabang sama-sama teks bebas ("SBY-OFC-01"), sedangkan
     * tanda hubung adalah pemisah bagian di kode aset — dibiarkan, sebuah kode
     * cabang berisi hubung akan mengaburkan batas antarbagian.
     */
    public static function segment(?string $value): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

        return $clean !== '' ? $clean : self::FALLBACK;
    }
}
