<?php

use App\Http\Middleware\EnsureMobileDevice;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'mobile.device' => EnsureMobileDevice::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        // The fingerprint machine speaks the ZKTeco iclock protocol and cannot send
        // a CSRF token; these device endpoints are gated by a serial-number allowlist.
        $middleware->validateCsrfTokens(except: ['iclock/*']);

        // Akun yang dinonaktifkan (mis. karyawan keluar) langsung dikeluarkan,
        // termasuk yang masih masuk lewat cookie "Ingat saya".
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
