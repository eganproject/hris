<?php

namespace App\Http\Middleware;

use App\Models\MobileDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga semua API aplikasi HP (dipasang sesudah auth:sanctum).
 *
 * Token harus milik HP yang masih AKTIF dan terikat ke karyawan si pengguna.
 * HP yang dicabut/diganti HR langsung tidak bisa dipakai, walau tokennya belum
 * kedaluwarsa. HP yang sedang dipakai tersedia di $request->attributes
 * ('mobile_device').
 */
class EnsureMobileDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token || ! method_exists($token, 'getAttribute')) {
            return response()->json(['message' => 'Sesi tidak valid. Silakan masuk lagi.', 'code' => 'unauthenticated'], 401);
        }

        if ($user->getAttribute('is_active') === false) {
            $token->delete();

            return response()->json(['message' => 'Akun Anda sudah tidak aktif. Hubungi HR bila ini keliru.', 'code' => 'account_inactive'], 401);
        }

        $name = (string) $token->getAttribute('name');
        $deviceId = str_starts_with($name, MobileDevice::TOKEN_PREFIX) ? (int) substr($name, strlen(MobileDevice::TOKEN_PREFIX)) : 0;
        $device = $deviceId ? MobileDevice::query()->with('employee')->find($deviceId) : null;

        $employee = $user->employee;

        if (! $device || ! $employee || $device->employee_id !== $employee->id || ! $device->isActive()) {
            $token->delete();

            return response()->json(['message' => 'HP ini tidak lagi terdaftar untuk akunmu. Silakan masuk lagi.', 'code' => 'device_revoked'], 401);
        }

        $header = $request->header('X-Device-Id');
        if ($header !== null && $header !== $device->device_uuid) {
            return response()->json(['message' => 'Token dipakai dari HP lain.', 'code' => 'device_mismatch'], 401);
        }

        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinutes(5))) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('mobile_device', $device);
        $request->attributes->set('employee', $employee);

        return $next($request);
    }
}
