<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup rute fitur yang sedang dimatikan di config/features.php.
 * Pemakaian: ->middleware('feature:kabar').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (config("features.{$feature}")) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Fitur ini sudah tidak tersedia. Perbarui aplikasimu.',
                'code' => 'feature_disabled',
            ], 410);
        }

        abort(404);
    }
}
