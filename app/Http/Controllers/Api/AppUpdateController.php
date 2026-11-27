<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AppReleaseController;
use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cek update aplikasi Android (tanpa Play Store).
 *
 * - Aplikasi HP karyawan: GET /api/app-update/employee?version_code=N, publik
 *   (APK-nya memang publik di /unduh-aplikasi) supaya layar wajib update juga
 *   bisa muncul sebelum login.
 * - Tablet absensi: GET /api/tablet/v1/app-update?version_code=N dengan token
 *   tablet; APK tablet hanya bisa diunduh dengan token itu juga.
 */
class AppUpdateController extends Controller
{
    public function employee(Request $request): JsonResponse
    {
        return response()->json(['data' => AppRelease::updateInfo(
            AppRelease::EMPLOYEE,
            $this->currentVersion($request),
            route('app-download.apk'),
        )]);
    }

    public function tablet(Request $request): JsonResponse
    {
        return response()->json(['data' => AppRelease::updateInfo(
            AppRelease::TABLET,
            $this->currentVersion($request),
            route('api.tablet.app-update.apk'),
        )]);
    }

    public function tabletApk(): StreamedResponse
    {
        $release = AppRelease::latestFor(AppRelease::TABLET);
        abort_if($release === null, 404, 'Aplikasi tablet belum diunggah.');

        return AppReleaseController::stream($release);
    }

    private function currentVersion(Request $request): int
    {
        return (int) $request->validate(['version_code' => ['required', 'integer', 'min:0']])['version_code'];
    }
}
