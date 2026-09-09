<?php

namespace App\Enums;

/**
 * Posisi sebuah aset dalam siklus hidupnya — "sedang berada pada proses apa".
 *
 * Bukan keadaan fisiknya: itu urusan AssetCondition. Sebuah laptop bisa berstatus
 * assigned sekaligus berkondisi damaged, dan keduanya perlu tercatat terpisah.
 */
enum AssetStatus: string
{
    case Draft = 'draft';             // Registrasi belum final
    case Available = 'available';     // Siap diserahkan
    case Assigned = 'assigned';       // Sedang dipegang karyawan
    case InUse = 'in_use';            // Terpakai bersama, tanpa satu pemegang
    case Maintenance = 'maintenance'; // Sedang diservis
    case Lost = 'lost';               // Hilang, belum ditemukan
    case Retired = 'retired';         // Tidak dipakai lagi, masih tercatat
    case Disposed = 'disposed';       // Sudah dilepas — hanya bisa dibaca

    /**
     * Status yang boleh dipilih langsung dari formulir master.
     *
     * Assigned dan Disposed sengaja tidak ada di sini: keduanya hanya boleh lahir
     * dari alur kerjanya sendiri (penyerahan aset, dan persetujuan disposal). Kalau
     * boleh diketik di formulir, sebuah aset bisa berstatus "dipegang" tanpa ada
     * seorang pun yang tercatat memegangnya.
     *
     * InUse justru sebaliknya: ia ADA di sini karena memang tidak punya alur kerja.
     * Barang pakai bersama tidak diserahterimakan ke siapa pun, jadi satu-satunya
     * cara menandainya adalah lewat formulir.
     *
     * @var list<self>
     */
    public const MANUAL = [self::Draft, self::Available, self::InUse, self::Maintenance, self::Lost, self::Retired];

    /** Status yang menutup siklus: barisnya tidak boleh disunting lagi. */
    public const CLOSED = [self::Disposed];

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Available => 'Tersedia',
            self::Assigned => 'Dipegang',
            self::InUse => 'Dipakai',
            self::Maintenance => 'Perawatan',
            self::Lost => 'Hilang',
            self::Retired => 'Tidak Dipakai',
            self::Disposed => 'Dilepas',
        };
    }

    /** Maps to the <x-status-badge> colour set. */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Assigned => 'info',
            // Warna sendiri, bukan menumpang warna Assigned: "Dipegang" bisa ditagih
            // ke satu orang, "Dipakai" tidak. Dua badge sewarna membuat pembaca daftar
            // menyangka keduanya sama-sama punya penanggung jawab.
            self::InUse => 'accent',
            self::Maintenance, self::Draft => 'warning',
            self::Lost => 'danger',
            self::Retired, self::Disposed => 'neutral',
        };
    }

    /**
     * Aset hanya boleh diserahkan ketika benar-benar menganggur di gudang.
     *
     * InUse tidak ikut, meski barangnya "sedang terpakai": justru karena terpakai
     * bersama, menyerahkannya ke satu orang mengubah sifat barangnya. Kembalikan
     * dulu ke Tersedia lewat formulir, supaya perpindahan dari pakai bersama ke
     * pemegang tunggal adalah keputusan yang dicatat, bukan efek samping.
     */
    public function isAssignable(): bool
    {
        return $this === self::Available;
    }

    /**
     * Barang pakai bersama: dipegang banyak orang, biasanya menetap di satu ruangan,
     * sehingga tidak ada satu karyawan pun yang bisa dimintai pertanggungjawaban
     * lewat serah terima. Karena itu ia bukan Assigned, dan bukan pula Tersedia.
     */
    public function isSharedUse(): bool
    {
        return $this === self::InUse;
    }

    public function isClosed(): bool
    {
        return in_array($this, self::CLOSED, true);
    }

    /** @return array<string, string> value => label */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }

    /** @return array<string, string> Pilihan untuk formulir master. */
    public static function manualLabels(): array
    {
        return collect(self::MANUAL)
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /** @return list<string> */
    public static function manualValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::MANUAL);
    }
}
