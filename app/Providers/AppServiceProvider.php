<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Seluruh antarmuka berbahasa Indonesia, tapi nama bulan/hari dari Carbon
        // masih ikut APP_LOCALE=en — sehingga "Agustus 2026" tampil sebagai
        // "August 2026" di roster, template Excel, dan notifikasi. Locale Carbon
        // disetel sendiri, bukan lewat APP_LOCALE, supaya Laravel tidak mulai
        // mencari berkas terjemahan id yang memang tidak ada di proyek ini.
        Carbon::setLocale('id');

        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
