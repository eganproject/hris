<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
| Helper bersama untuk tes API aplikasi HP tahap 2.
|
| Rute tahap 2 di-require oleh routes/api.php milik tahap 1 di dalam grup
| /api/mobile/v1 + auth:sanctum + mobile.device. Tes di sini menguji aturan
| bisnis tahap 2, jadi bila grup itu belum terpasang (mis. branch tahap 1 belum
| digabung) rutenya didaftarkan sendiri di belakang middleware auth biasa.
*/

function mobileTahap2Routes(): void
{
    if (Route::has('mobile.profile.show')) {
        return;
    }

    Route::prefix('api/mobile/v1')->middleware(['api', 'auth'])->group(base_path('routes/api_mobile_tahap2.php'));
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
}

/** Masuk sebagai pengguna untuk API HP, apa pun guard yang dipakai tahap 1. */
function mobileAs(User $user): TestCase
{
    mobileTahap2Routes();

    if (class_exists(Sanctum::class) && config('auth.guards.sanctum')) {
        Sanctum::actingAs($user, ['*']);
    }

    $test = test()->actingAs($user);

    // Middleware pengikat HP milik tahap 1 diuji di tes tahap 1, bukan di sini.
    foreach (['App\\Http\\Middleware\\EnsureMobileDevice', 'App\\Http\\Middleware\\MobileDevice'] as $class) {
        if (class_exists($class)) {
            $test->withoutMiddleware($class);
        }
    }

    return $test;
}

/**
 * Karyawan + akun login.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: User, 1: Employee}
 */
function mobileEmployee(string $name, array $attributes = []): array
{
    $user = User::factory()->create(['name' => $name]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'full_name' => $name,
        'employment_status' => 'active',
        ...$attributes,
    ]);

    return [$user, $employee];
}
