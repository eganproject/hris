<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nonaktifkan otomatis karyawan yang masa kontraknya sudah berakhir, setiap hari.
Schedule::command('employees:deactivate-expired')->dailyAt('00:05');
