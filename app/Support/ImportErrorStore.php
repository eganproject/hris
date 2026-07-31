<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Short-lived storage for a rejected import: the file the user uploaded plus the
 * structured problems found in it, keyed by a one-off token. The upload request
 * only flashes the token; the annotated workbook is rebuilt on demand when the
 * user clicks "unduh rincian kesalahan".
 *
 * Reports older than a day are pruned on each write, so nothing accumulates.
 */
class ImportErrorStore
{
    private const DIR = 'import-error-reports';

    /**
     * @param  list<array{row: ?int, column: ?string, message: string}>  $errors
     * @return string the token the download route resolves
     */
    public static function put(UploadedFile $file, array $errors): string
    {
        $token = (string) Str::uuid();

        foreach (Storage::files(self::DIR) as $old) {
            if (Storage::lastModified($old) < now()->subDay()->getTimestamp()) {
                Storage::delete($old);
            }
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $source = self::DIR."/{$token}.{$extension}";
        Storage::put($source, file_get_contents($file->getRealPath()));

        Storage::put(self::DIR."/{$token}.json", json_encode([
            'source' => $source,
            'original_name' => $file->getClientOriginalName(),
            'errors' => $errors,
        ]));

        return $token;
    }

    /**
     * Rebuild the annotated workbook for a token, or 404 when it has expired.
     *
     * @param  int  $headerRow  1-indexed row holding the column headers in the upload
     */
    public static function download(string $token, int $headerRow = 1): BinaryFileResponse
    {
        abort_unless(Str::isUuid($token), 404);

        $payloadPath = self::DIR."/{$token}.json";

        abort_unless(Storage::exists($payloadPath), 404);

        /** @var array{source: string, original_name: string, errors: list<array{row: ?int, column: ?string, message: string}>} $payload */
        $payload = json_decode(Storage::get($payloadPath), true);

        abort_unless(is_array($payload) && Storage::exists($payload['source']), 404);

        return ImportErrorReport::download(
            Storage::path($payload['source']),
            $payload['errors'],
            'kesalahan-import-'.pathinfo($payload['original_name'], PATHINFO_FILENAME).'.xlsx',
            $headerRow,
        );
    }
}
