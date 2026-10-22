<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan akun yang sudah dinonaktifkan dari setiap request.
 *
 * Login memang menolak akun nonaktif, tapi sesi yang masih hidup dan cookie
 * "Ingat saya" tidak melewati pemeriksaan login lagi — tanpa ini karyawan yang
 * sudah keluar tetap bisa membuka HRIS selama cookie-nya berlaku.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->getAttribute('is_active') === false) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $message = 'Akun Anda sudah tidak aktif. Hubungi HR bila ini keliru.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
