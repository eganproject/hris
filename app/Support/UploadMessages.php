<?php

namespace App\Support;

/**
 * Kalimat penolakan unggahan berkas, satu tempat untuk seluruh aplikasi.
 *
 * Tanpa berkas lang/ sendiri, aturan seperti "mimes" dan "max" jatuh ke pesan
 * bawaan Laravel yang berbahasa Inggris dan berbicara dalam kilobyte — "The photo
 * field must not be greater than 2048 kilobytes." Halaman yang sudah menuliskan
 * pesannya sendiri jadi berbahasa Indonesia, yang belum tidak; dan tiap kali batas
 * ukurannya diubah, kalimatnya harus dikejar satu per satu.
 *
 * Kelas ini menyeragamkannya: pesannya menyebut apa yang diterima dan berapa
 * batasnya dalam MB — satuan yang sama dengan yang tertulis di layar dan yang
 * dipakai penjaga sisi klien (lihat resources/js/file-guard.js).
 */
final class UploadMessages
{
    /** Batas ukuran berkas Excel untuk impor, dalam MB. */
    public const EXCEL_MAX_MB = 10;

    /**
     * Aturan untuk berkas Excel impor (data karyawan & roster).
     *
     * @return list<string>
     */
    public static function excelRules(): array
    {
        return ['required', 'file', 'mimes:xlsx,xls,csv', 'max:'.(self::EXCEL_MAX_MB * 1024)];
    }

    /**
     * @return array<string, string>
     */
    public static function excel(string $field = 'file'): array
    {
        return [
            "{$field}.required" => 'Pilih berkas Excel-nya dulu.',
            "{$field}.file" => 'Unggahan gagal terkirim. Coba pilih berkasnya lagi.',
            "{$field}.mimes" => 'Berkas harus berformat Excel (.xlsx, .xls) atau CSV (.csv).',
            "{$field}.max" => 'Ukuran berkas maksimal '.self::EXCEL_MAX_MB.' MB.',
            "{$field}.uploaded" => 'Berkas gagal diunggah — kemungkinan ukurannya melebihi batas server. Coba perkecil berkasnya.',
        ];
    }

    /**
     * Dokumen PDF (mis. dokumen kontrak). $label dipakai di awal kalimat, jadi
     * tuliskan sebagaimana ia muncul di layar: "Dokumen kontrak".
     *
     * @return array<string, string>
     */
    public static function pdf(string $field, int $maxMb, string $label = 'Dokumen'): array
    {
        return [
            "{$field}.file" => "{$label} gagal terkirim. Coba pilih berkasnya lagi.",
            "{$field}.mimes" => "{$label} harus berupa berkas PDF.",
            "{$field}.max" => "Ukuran {$label} maksimal {$maxMb} MB.",
            "{$field}.uploaded" => "{$label} gagal diunggah — kemungkinan ukurannya melebihi batas server. Coba perkecil berkasnya.",
        ];
    }

    /**
     * Foto (foto karyawan, foto profil, selfie absen).
     *
     * @param  list<string>  $formats  format yang diterima, sebagaimana ditulis ke pengguna
     * @return array<string, string>
     */
    public static function photo(string $field, int $maxMb, string $label = 'Foto', array $formats = ['JPG', 'PNG', 'WebP'], ?string $dimensions = null): array
    {
        $list = implode(', ', $formats);

        return array_filter([
            "{$field}.required" => "Pilih berkas {$label}-nya dulu.",
            "{$field}.image" => "{$label} harus berupa gambar ({$list}).",
            "{$field}.mimes" => "{$label} harus berformat {$list}.",
            "{$field}.max" => "Ukuran {$label} maksimal {$maxMb} MB.",
            "{$field}.dimensions" => $dimensions,
            "{$field}.uploaded" => "{$label} gagal diunggah — kemungkinan ukurannya melebihi batas server. Coba perkecil berkasnya.",
        ]);
    }

    /**
     * Lampiran yang boleh gambar atau PDF (lampiran cuti).
     *
     * @return array<string, string>
     */
    public static function attachment(string $field, int $maxMb, string $label = 'Lampiran'): array
    {
        return [
            "{$field}.file" => "{$label} gagal terkirim. Coba pilih berkasnya lagi.",
            "{$field}.mimes" => "{$label} harus berupa gambar (JPG, PNG, WEBP) atau PDF.",
            "{$field}.max" => "Ukuran {$label} maksimal {$maxMb} MB.",
            "{$field}.uploaded" => "{$label} gagal diunggah — kemungkinan ukurannya melebihi batas server. Coba perkecil berkasnya.",
        ];
    }
}
