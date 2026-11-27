<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman publik /unduh-aplikasi: karyawan mengunduh APK aplikasi HP langsung
 * dari web HRIS (tanpa GitHub / Play Store). Hanya aplikasi HP karyawan; APK
 * tablet absensi tidak pernah disajikan di sini.
 */
class AppDownloadController extends Controller
{
    public function page(): View
    {
        return view('app-download', [
            'release' => AppRelease::latestFor(AppRelease::EMPLOYEE),
        ]);
    }

    public function download(): StreamedResponse
    {
        $release = AppRelease::latestFor(AppRelease::EMPLOYEE);
        abort_if($release === null, 404, 'Aplikasi HP karyawan belum diunggah.');

        return AppReleaseController::stream($release);
    }
}
