<?php

use App\Models\DeviceCommunication;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nonaktifkan otomatis karyawan yang masa kontraknya sudah berakhir, setiap hari.
Schedule::command('employees:deactivate-expired')->dailyAt('00:05');

// Ingatkan HR untuk kontrak yang akan berakhir (H-30, H-14, H-7), setiap pagi.
Schedule::command('contracts:notify-expiring')->dailyAt('06:00');

// Deteksi mesin absensi yang offline dan beri tahu HR (sekali per gangguan).
Schedule::command('devices:notify-offline')->everyFifteenMinutes();

// Pangkas log komunikasi mesin agar tabel tetap ramping (simpan 14 hari terakhir).
Schedule::call(function () {
    DeviceCommunication::query()->where('created_at', '<', now()->subDays(14))->delete();
})->dailyAt('00:10')->name('prune-device-communications');

// Pangkas notifikasi yang sudah dibaca lebih dari 30 hari agar tabel tetap ramping.
Schedule::call(function () {
    Illuminate\Notifications\DatabaseNotification::query()
        ->whereNotNull('read_at')
        ->where('read_at', '<', now()->subDays(30))
        ->delete();
})->dailyAt('00:15')->name('prune-read-notifications');
