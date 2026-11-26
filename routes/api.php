<?php

use App\Http\Controllers\Api\Mobile\AttendanceController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\CorrectionController;
use App\Http\Controllers\Api\Mobile\PushTokenController;
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
        Route::post('auth/consent', [AuthController::class, 'consent'])->middleware('feature:mobile_clock')->name('auth.consent');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        // Token Firebase untuk notifikasi saat aplikasi ditutup.
        Route::post('push-token', [PushTokenController::class, 'store'])->name('push-token.store');
        Route::delete('push-token', [PushTokenController::class, 'destroy'])->name('push-token.destroy');

        // Tahap 1: absensi. Absen dari HP dimatikan (config/features.php);
        // status hari ini dan riwayat tetap dipakai aplikasi.
        Route::get('attendance/status', [AttendanceController::class, 'status'])->name('attendance.status');
        Route::post('attendance/clock', [AttendanceController::class, 'clock'])->middleware('feature:mobile_clock')->name('attendance.clock');
        Route::get('attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
        Route::get('attendance/{attendance}/photo/{side}', [AttendanceController::class, 'photo'])
            ->middleware('feature:mobile_clock')
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

/*
| API tablet absensi wajah (aplikasi di repo hris-mobile, folder tablet/).
| Tablet memegang token Sanctum sendiri; lihat EnsureAttendanceTablet.
*/
Route::prefix('tablet/v1')->name('api.tablet.')->group(function () {
    Route::post('pair', [\App\Http\Controllers\Api\Tablet\TabletController::class, 'pair'])
        ->middleware('throttle:10,1')
        ->name('pair');

    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureAttendanceTablet::class, 'throttle:300,1'])->group(function () {
        Route::get('sync', [\App\Http\Controllers\Api\Tablet\TabletController::class, 'sync'])->name('sync');
        Route::post('attendance', [\App\Http\Controllers\Api\Tablet\AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('faces', [\App\Http\Controllers\Api\Tablet\FaceController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('faces.store');
    });
});
