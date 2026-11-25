<?php

/*
|--------------------------------------------------------------------------
| API aplikasi HP — Tahap 2 (pengajuan, jadwal, notifikasi, profil, atasan,
| Kabar, struktur organisasi)
|--------------------------------------------------------------------------
|
| File ini di-require oleh routes/api.php (Tahap 1) DI DALAM grup prefix
| /api/mobile/v1 + middleware auth:sanctum & mobile.device, jadi semua rute di
| sini sudah terautentikasi dan terikat ke HP terdaftar. Grup tahap 1 memberi
| awalan nama "api.mobile.", jadi rute di sini dinamai tanpa awalan.
|
*/

use App\Http\Controllers\Api\Mobile\Hr\ApprovalController;
use App\Http\Controllers\Api\Mobile\Hr\KabarController;
use App\Http\Controllers\Api\Mobile\Hr\NotificationController;
use App\Http\Controllers\Api\Mobile\Hr\OrgController;
use App\Http\Controllers\Api\Mobile\Hr\OvertimeController;
use App\Http\Controllers\Api\Mobile\Hr\PengajuanController;
use App\Http\Controllers\Api\Mobile\Hr\ProfileController;
use App\Http\Controllers\Api\Mobile\Hr\ScheduleController;
use App\Http\Controllers\Api\Mobile\Hr\SwapController;
use App\Http\Controllers\Api\Mobile\Hr\TeamController;
use App\Http\Controllers\Api\Mobile\Hr\TeamScheduleController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    // Pengajuan (cuti/izin/sakit, lembur, tukar jadwal; koreksi milik Tahap 1)
    Route::get('pengajuan', [PengajuanController::class, 'index'])->name('pengajuan.index');
    Route::get('pengajuan/ringkasan', [PengajuanController::class, 'summary'])->name('pengajuan.summary');
    Route::get('leave-types', [PengajuanController::class, 'leaveTypes'])->name('leave-types');
    Route::post('leave', [PengajuanController::class, 'storeLeave'])->name('leave.store');
    Route::get('leave/{leaveRequest}', [PengajuanController::class, 'showLeave'])->name('leave.show');
    Route::post('leave/{leaveRequest}/cancel', [PengajuanController::class, 'cancelLeave'])->name('leave.cancel');
    Route::delete('leave/{leaveRequest}', [PengajuanController::class, 'destroyLeave'])->name('leave.destroy');

    Route::get('overtime', [OvertimeController::class, 'index'])->name('overtime.index');
    Route::post('overtime', [OvertimeController::class, 'store'])->name('overtime.store');
    Route::delete('overtime/{overtime}', [OvertimeController::class, 'destroy'])->name('overtime.destroy');

    Route::get('swaps', [SwapController::class, 'index'])->name('swaps.index');
    Route::get('swaps/colleagues', [SwapController::class, 'colleagues'])->name('swaps.colleagues');
    Route::post('swaps', [SwapController::class, 'store'])->name('swaps.store');
    Route::post('swaps/{swap}/respond', [SwapController::class, 'respond'])->name('swaps.respond');
    Route::delete('swaps/{swap}', [SwapController::class, 'destroy'])->name('swaps.destroy');

    // Jadwal saya
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('schedule/confirm', [ScheduleController::class, 'confirm'])->name('schedule.confirm');

    // Notifikasi
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Profil & struktur organisasi
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('org', [OrgController::class, 'show'])->name('org.show');

    // Menu atasan (karyawan yang punya bawahan; HR bisa mengambil alih persetujuan)
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{kind}/{id}/approve', [ApprovalController::class, 'approve'])->whereIn('kind', ['leave', 'overtime'])->whereNumber('id')->name('approvals.approve');
    Route::post('approvals/{kind}/{id}/reject', [ApprovalController::class, 'reject'])->whereIn('kind', ['leave', 'overtime'])->whereNumber('id')->name('approvals.reject');
    Route::get('team/today', [TeamController::class, 'today'])->name('team.today');
    Route::post('team/{employee}/remind', [TeamController::class, 'remind'])->name('team.remind');
    Route::get('team/schedule', [TeamScheduleController::class, 'show'])->name('team.schedule');
    Route::put('team/schedule', [TeamScheduleController::class, 'update'])->name('team.schedule.update');
    Route::post('team/schedule/copy-last-week', [TeamScheduleController::class, 'copyLastWeek'])->name('team.schedule.copy');
    Route::post('team/schedule/publish', [TeamScheduleController::class, 'publish'])->name('team.schedule.publish');
    Route::get('shifts', [TeamScheduleController::class, 'shifts'])->name('shifts');

    // Kabar
    Route::get('kabar', [KabarController::class, 'feed'])->name('kabar.feed');
    Route::get('announcements', [KabarController::class, 'announcements'])->name('announcements.index');
    Route::get('announcements/{announcement}', [KabarController::class, 'announcement'])->name('announcements.show');
    Route::post('announcements/{announcement}/read', [KabarController::class, 'markRead'])->name('announcements.read');
    Route::get('announcements/{announcement}/attachment', [KabarController::class, 'announcementAttachment'])->name('announcements.attachment');
    Route::post('posts', [KabarController::class, 'storePost'])->name('posts.store');
    Route::get('posts/{post}', [KabarController::class, 'showPost'])->name('posts.show');
    Route::delete('posts/{post}', [KabarController::class, 'destroyPost'])->name('posts.destroy');
    Route::get('posts/{post}/image', [KabarController::class, 'postImage'])->name('posts.image');
    Route::post('posts/{post}/like', [KabarController::class, 'likePost'])->name('posts.like');
    Route::post('posts/{post}/report', [KabarController::class, 'reportPost'])->name('posts.report');
    Route::post('posts/{post}/comments', [KabarController::class, 'storeComment'])->name('posts.comments.store');
    Route::delete('comments/{comment}', [KabarController::class, 'destroyComment'])->name('comments.destroy');
    Route::post('comments/{comment}/like', [KabarController::class, 'likeComment'])->name('comments.like');
    Route::post('comments/{comment}/report', [KabarController::class, 'reportComment'])->name('comments.report');
});
