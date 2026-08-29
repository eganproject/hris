<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use App\Observers\ActivityObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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

        $this->recordActivity();
    }

    /**
     * Pasang pencatat jejak aktivitas: perubahan data induk lewat observer, dan
     * kejadian autentikasi lewat event bawaan Laravel.
     */
    private function recordActivity(): void
    {
        foreach (array_keys(ActivityObserver::WATCHED) as $model) {
            $model::observe(ActivityObserver::class);
        }

        Event::listen(Login::class, [RecordAuthActivity::class, 'handleLogin']);
        Event::listen(Logout::class, [RecordAuthActivity::class, 'handleLogout']);
        Event::listen(Failed::class, [RecordAuthActivity::class, 'handleFailed']);
        Event::listen(Lockout::class, [RecordAuthActivity::class, 'handleLockout']);
    }
}
