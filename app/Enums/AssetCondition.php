<?php

namespace App\Enums;

/**
 * Keadaan fisik aset — "barangnya bagaimana", terlepas dari sedang dipakai siapa.
 * Dipakai saat registrasi, serah-terima, pengembalian, dan inspeksi maintenance.
 */
enum AssetCondition: string
{
    case New = 'new';           // Baru, belum pernah dipakai
    case Good = 'good';         // Layak pakai
    case Fair = 'fair';         // Layak, ada cacat pemakaian
    case Damaged = 'damaged';   // Rusak, perlu perbaikan
    case Unusable = 'unusable'; // Tidak bisa dipakai lagi

    /**
     * Kondisi yang masih layak diserahkan kepada karyawan.
     *
     * @var list<self>
     */
    public const SERVICEABLE = [self::New, self::Good, self::Fair];

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Good => 'Baik',
            self::Fair => 'Cukup',
            self::Damaged => 'Rusak',
            self::Unusable => 'Tidak Layak',
        };
    }

    /** Maps to the <x-status-badge> colour set. */
    public function tone(): string
    {
        return match ($this) {
            self::New, self::Good => 'success',
            self::Fair => 'warning',
            self::Damaged => 'danger',
            self::Unusable => 'neutral',
        };
    }

    public function isServiceable(): bool
    {
        return in_array($this, self::SERVICEABLE, true);
    }

    /** @return array<string, string> value => label */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $condition) => [$condition->value => $condition->label()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $condition) => $condition->value, self::cases());
    }
}
