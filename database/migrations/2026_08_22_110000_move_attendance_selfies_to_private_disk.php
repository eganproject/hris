<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Memindahkan foto selfie absensi dari disk publik ke disk privat.
 *
 * Sebelumnya foto tersimpan di storage/app/public dan disajikan lewat symlink, jadi
 * siapa pun yang tahu URL-nya bisa membukanya tanpa login. Foto wajah karyawan beserta
 * koordinat tempat ia bekerja setidaknya sepadan dengan lampiran surat cuti, yang sejak
 * awal disimpan privat. Path di database tidak berubah — hanya disknya.
 *
 * Aman dijalankan berulang: berkas yang sudah pindah tidak akan disalin lagi.
 */
return new class extends Migration
{
    /** Folder yang isinya ikut pindah, relatif terhadap akar disk. */
    private const DIRECTORIES = ['attendance/selfies', 'attendance/selfie-tests'];

    public function up(): void
    {
        $this->move(from: 'public', to: 'local');
    }

    public function down(): void
    {
        $this->move(from: 'local', to: 'public');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);
        $moved = 0;

        foreach (self::DIRECTORIES as $directory) {
            if (! $source->exists($directory)) {
                continue;
            }

            foreach ($source->allFiles($directory) as $path) {
                // Sudah ada di tujuan (migrasi diulang, atau sebagian sudah pindah):
                // cukup bersihkan sisa di sumbernya.
                if (! $target->exists($path)) {
                    $target->put($path, $source->get($path));
                }

                $source->delete($path);
                $moved++;
            }

            $source->deleteDirectory($directory);
        }

        if ($moved > 0) {
            echo "  {$moved} foto selfie dipindahkan ke disk '{$to}'.".PHP_EOL;
        }
    }
};
