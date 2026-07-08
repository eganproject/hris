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

// Pangkas log komunikasi mesin agar tabel tetap ramping (simpan 14 hari terakhir).
Schedule::call(function () {
    DeviceCommunication::query()->where('created_at', '<', now()->subDays(14))->delete();
})->dailyAt('00:10')->name('prune-device-communications');
