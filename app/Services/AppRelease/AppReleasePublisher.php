<?php

namespace App\Services\AppRelease;

use App\Models\AppRelease;
use App\Support\ApkInfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Menyimpan APK baru: membaca nama paket + versi dari manifest APK, menolak APK
 * aplikasi lain (mis. APK tablet diunggah ke slot aplikasi HP), lalu menyimpan
 * berkasnya di disk privat.
 */
class AppReleasePublisher
{
    public function publish(string $app, UploadedFile $file, ?string $notes, ?int $userId, string $source = 'upload'): AppRelease
    {
        if (! isset(AppRelease::APPS[$app])) {
            throw ValidationException::withMessages(['app' => 'Jenis aplikasi tidak dikenal.']);
        }

        try {
            $info = ApkInfo::read($file->getRealPath());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['apk' => $e->getMessage()]);
        }

        $expected = AppRelease::APPS[$app]['package'];
        if ($info->package !== $expected) {
            $other = collect(AppRelease::APPS)->firstWhere('package', $info->package);
            $hint = $other ? ' Ini APK '.mb_strtolower($other['label']).'.' : '';

            throw ValidationException::withMessages([
                'apk' => 'APK ini bukan '.mb_strtolower(AppRelease::APPS[$app]['label'])." (paket {$info->package}).{$hint}",
            ]);
        }

        $versionName = $info->versionName ?: (string) $info->versionCode;
        $path = $file->storeAs(
            "app-releases/{$app}",
            Str::slug($versionName).'-'.$info->versionCode.'-'.Str::lower(Str::random(8)).'.apk',
            AppRelease::DISK,
        );

        return AppRelease::query()->create([
            'app' => $app,
            'package_name' => $info->package,
            'version_code' => $info->versionCode,
            'version_name' => mb_substr($versionName, 0, 50),
            'file_path' => $path,
            'file_size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', Storage::disk(AppRelease::DISK)->path($path)),
            'notes' => $notes ?: null,
            'uploaded_by' => $userId,
            'source' => $source,
        ]);
    }
}
