<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Services\AppRelease\AppReleasePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /api/app-releases — dipanggil GitHub Actions (repo hris-mobile) setelah
 * APK selesai dibangun, supaya versi baru langsung muncul di HRIS tanpa unggah
 * manual. Dijaga token APP_RELEASE_PUBLISH_TOKEN di .env; tanpa token, rute ini
 * mati (404).
 */
class AppReleasePublishController extends Controller
{
    public function __invoke(Request $request, AppReleasePublisher $publisher): JsonResponse
    {
        $token = (string) config('services.app_release.publish_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Token publikasi salah.');

        $data = $request->validate([
            'app' => ['required', Rule::in(array_keys(AppRelease::APPS))],
            'apk' => ['required', 'file', 'max:'.(200 * 1024)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $release = $publisher->publish($data['app'], $request->file('apk'), $data['notes'] ?? null, null, 'ci');

        return response()->json([
            'message' => "{$release->label()} versi {$release->version_name} tersimpan.",
            'data' => [
                'id' => $release->id,
                'app' => $release->app,
                'version_name' => $release->version_name,
                'version_code' => $release->version_code,
            ],
        ], 201);
    }
}
