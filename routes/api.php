<?php

use App\Http\Controllers\Api\Mobile\AttendanceController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\CorrectionController;
use Illuminate\Support\Facades\Route;

/*
| API aplikasi HP (Flutter, repo eganproject/hris-mobile).
| Semua rute di /api/mobile/v1. Rute yang butuh login memakai
| ['auth:sanctum', 'mobile.device'] — token terikat ke HP yang aktif.
*/
Route::prefix('mobile/v1')->name('api.mobile.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'mobile.device', 'throttle:120,1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/consent', [AuthController::class, 'consent'])->name('auth.consent');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        // Tahap 1: absensi.
        Route::get('attendance/status', [AttendanceController::class, 'status'])->name('attendance.status');
        Route::post('attendance/clock', [AttendanceController::class, 'clock'])->name('attendance.clock');
        Route::get('attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
        Route::get('attendance/{attendance}/photo/{side}', [AttendanceController::class, 'photo'])
            ->whereIn('side', ['in', 'out'])
            ->name('attendance.photo');

        Route::get('corrections', [CorrectionController::class, 'index'])->name('corrections.index');
        Route::post('corrections', [CorrectionController::class, 'store'])->name('corrections.store');

        // Tahap 2 (pengajuan, jadwal, notifikasi, profil, fitur atasan, Kabar,
        // struktur organisasi) dikerjakan terpisah di file ini.
        if (file_exists(__DIR__.'/api_mobile_tahap2.php')) {
            require __DIR__.'/api_mobile_tahap2.php';
        }
    });
});
