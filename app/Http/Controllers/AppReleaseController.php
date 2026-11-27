<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use App\Services\AppRelease\AppReleasePublisher;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu System > Aplikasi Android (khusus superadmin): unggah APK aplikasi HP
 * karyawan dan APK tablet absensi wajah, lalu unduh APK tablet untuk dipasang
 * di tablet kantor. APK tablet tidak pernah bisa diunduh dari halaman publik.
 */
class AppReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $apps = [];
        foreach (AppRelease::APPS as $key => $meta) {
            $apps[$key] = $meta + [
                'latest' => AppRelease::latestFor($key),
                'releases' => AppRelease::query()
                    ->with('uploader:id,name')
                    ->where('app', $key)
                    ->orderByDesc('version_code')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get(),
            ];
        }

        return view('android-apps.index', [
            'apps' => $apps,
            'publicPageUrl' => route('app-download.page'),
            'maxUploadMb' => self::maxUploadMb(),
            // Situs berada di belakang Cloudflare yang menolak permintaan >100 MB.
            'maxClientMb' => min(self::maxUploadMb() ?: 100, 99),
        ]);
    }

    public function store(Request $request, AppReleasePublisher $publisher): RedirectResponse|JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        try {
            return $this->storeRelease($request, $publisher);
        } catch (ValidationException $e) {
            // Aplikasi hanya merender JSON untuk api/*; unggahan XHR halaman ini
            // butuh galatnya sebagai JSON agar bisa ditampilkan tanpa muat ulang.
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            throw $e;
        }
    }

    private function storeRelease(Request $request, AppReleasePublisher $publisher): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'app' => ['required', Rule::in(array_keys(AppRelease::APPS))],
            'apk' => ['required', 'file', 'max:'.(200 * 1024)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'apk.required' => 'Pilih berkas APK yang akan diunggah.',
            'apk.uploaded' => 'APK gagal diunggah. Ukurannya mungkin melebihi batas upload server ('.self::maxUploadMb().' MB).',
            'apk.max' => 'APK maksimal 200 MB.',
        ]);

        $release = $publisher->publish($data['app'], $request->file('apk'), $data['notes'] ?? null, $request->user()->id);
        ActivityLogger::log('app-releases', 'create', "Mengunggah {$release->label()} versi {$release->version_name}.", $release);

        $latest = AppRelease::latestFor($release->app);
        $message = "{$release->label()} versi {$release->version_name} berhasil diunggah.";
        if (! $latest?->is($release)) {
            $message .= " Versi {$latest?->version_name} lebih baru, jadi itu yang tetap diunduh.";
        }

        // Halaman mengunggah lewat XHR (bilah kemajuan); hasilnya ditampilkan
        // setelah halaman dimuat ulang dari pesan sesi yang sama.
        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json(['message' => $message, 'redirect' => route('android-apps.index')]);
        }

        return redirect()->route('android-apps.index')->with('status', $message);
    }

    public function download(Request $request, AppRelease $release): StreamedResponse
    {
        $this->authorizeSuperAdmin($request);

        return self::stream($release);
    }

    public function destroy(Request $request, AppRelease $release): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        Storage::disk(AppRelease::DISK)->delete($release->file_path);
        $release->delete();
        ActivityLogger::log('app-releases', 'delete', "Menghapus {$release->label()} versi {$release->version_name}.");

        return redirect()->route('android-apps.index')->with('status', "{$release->label()} versi {$release->version_name} dihapus.");
    }

    public static function stream(AppRelease $release): StreamedResponse
    {
        abort_unless(Storage::disk(AppRelease::DISK)->exists($release->file_path), 404, 'Berkas APK tidak ditemukan di server.');

        return Storage::disk(AppRelease::DISK)->download(
            $release->file_path,
            $release->downloadName(),
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Halaman ini khusus superadmin.');
    }

    /** Batas upload PHP (upload_max_filesize / post_max_size), dalam MB. */
    public static function maxUploadMb(): int
    {
        $toBytes = function (string $value): int {
            $value = trim($value);
            $number = (int) $value;

            return match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            };
        };

        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
        ]);

        return $limits ? (int) floor(min($limits) / 1024 ** 2) : 0;
    }
}
