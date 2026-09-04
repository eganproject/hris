<?php

namespace App\Enums;

/**
 * Jenis kejadian pada garis waktu sebuah aset.
 *
 * Nilainya sengaja menggambarkan APA YANG TERJADI pada barangnya, bukan kolom mana
 * yang berubah — yang membaca halaman ini orang gudang, bukan pemeriksa basis data.
 */
enum AssetTransactionType: string
{
    case Assigned = 'assigned';         // Diserahkan kepada karyawan
    case Acknowledged = 'acknowledged'; // Karyawan mengakui menerimanya
    case Returned = 'returned';         // Dikembalikan ke perusahaan
    case Transferred = 'transferred';   // Berpindah lokasi atau pemegang

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Diserahkan',
            self::Acknowledged => 'Dikonfirmasi',
            self::Returned => 'Dikembalikan',
            self::Transferred => 'Dipindahkan',
        };
    }

    /** Maps to the <x-status-badge> colour set. */
    public function tone(): string
    {
        return match ($this) {
            self::Assigned => 'info',
            self::Acknowledged => 'success',
            self::Returned => 'neutral',
            self::Transferred => 'warning',
        };
    }
}
