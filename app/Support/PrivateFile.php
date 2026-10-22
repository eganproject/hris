<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan berkas unggahan dari disk privat dengan aman.
 *
 * Tipe berkas tidak pernah diambil dari kata browser pengunggah: tipe yang dikirim
 * browser bisa diisi apa saja, termasuk text/html, sehingga "PDF" berisi script
 * akan dijalankan browser peninjau (stored XSS). Yang disajikan inline hanya tipe
 * pada daftar di bawah; selebihnya diunduh sebagai berkas biasa. Header nosniff
 * mencegah browser menebak-nebak tipe lain dari isi berkasnya. (CSP sandbox sengaja
 * tidak dipakai: Chrome menolak menampilkan PDF yang disajikan dengan sandbox.)
 */
class PrivateFile
{
    /** Tipe yang aman ditampilkan langsung di tab browser. */
    public const INLINE_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** Tipe berkas menurut isinya (dideteksi server), bukan menurut pengunggah. */
    public static function detectedMime(UploadedFile $file): string
    {
        return $file->getMimeType() ?: 'application/octet-stream';
    }

    public static function response(Filesystem $disk, string $path, ?string $name, ?string $mime, bool $download = false): StreamedResponse
    {
        $mime = strtolower(trim((string) $mime));
        $inlineSafe = in_array($mime, self::INLINE_TYPES, true);

        $headers = [
            'Content-Type' => $inlineSafe ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $download || ! $inlineSafe
            ? $disk->download($path, $name, $headers)
            : $disk->response($path, $name, $headers);
    }
}
