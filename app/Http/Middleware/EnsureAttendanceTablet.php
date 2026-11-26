<?php

namespace App\Http\Middleware;

use App\Models\AttendanceTablet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API tablet absensi wajah (dipasang sesudah auth:sanctum).
 *
 * Token harus milik tablet yang masih AKTIF. Tablet yang dicabut HR langsung
 * tidak bisa dipakai. Setiap permintaan memperbarui "terakhir aktif".
 */
class EnsureAttendanceTablet
{
    public function handle(Request $request, Closure $next): Response
    {
        $tablet = $request->user();

        if (! $tablet instanceof AttendanceTablet) {
            return response()->json(['message' => 'Token ini bukan milik tablet absensi.', 'code' => 'unauthenticated'], 401);
        }

        if (! $tablet->isActive()) {
            $tablet->currentAccessToken()?->delete();

            return response()->json(['message' => 'Tablet ini sudah dicabut dari HRIS. Minta HR membuat kode pasangan baru.', 'code' => 'tablet_revoked'], 401);
        }

        if (! $tablet->last_seen_at || $tablet->last_seen_at->lt(now()->subSeconds(30))) {
            $tablet->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
