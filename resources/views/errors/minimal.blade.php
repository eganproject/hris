{{--
    Menimpa tata letak bawaan Laravel untuk halaman galat (403, 404, 419, 429,
    500, 503) supaya memakai logo dan bahasa aplikasi. Sengaja tanpa @vite:
    halaman ini juga dipakai saat aplikasi sedang rusak (500).
--}}
@php
    $code = trim($__env->yieldContent('code'));
    $message = trim($__env->yieldContent('message'));
    $friendly = [
        '401' => ['Perlu login', 'Silakan login terlebih dahulu untuk membuka halaman ini.'],
        '403' => ['Akses ditolak', 'Akun Anda belum punya izin untuk membuka halaman ini. Minta admin mengaturnya di Kontrol Akses.'],
        '404' => ['Halaman tidak ditemukan', 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.'],
        '419' => ['Sesi berakhir', 'Halaman terlalu lama dibiarkan terbuka. Muat ulang halaman lalu coba lagi.'],
        '429' => ['Terlalu banyak permintaan', 'Tunggu sebentar, lalu coba lagi.'],
        '500' => ['Terjadi kesalahan', 'Ada masalah di server. Coba lagi beberapa saat lagi.'],
        '503' => ['Sedang pemeliharaan', 'Aplikasi sedang diperbarui. Coba lagi beberapa saat lagi.'],
    ][$code] ?? [$message ?: 'Terjadi kesalahan', null];
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $friendly[0] }} - {{ config('app.name', 'HRIS') }}</title>
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/company-logo-192.png') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <style>
            :root { color-scheme: light; }
            * { box-sizing: border-box; }
            body {
                margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
                background: #f7f8fa; color: #0f172a; padding: 24px;
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            }
            .card {
                width: 100%; max-width: 28rem; background: #fff; border: 1px solid #e5e7eb;
                border-radius: 12px; padding: 32px; text-align: center;
                box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
            }
            .logo { width: 72px; height: 72px; border-radius: 9999px; display: block; margin: 0 auto; }
            .code { margin-top: 20px; font-size: 13px; font-weight: 600; letter-spacing: .08em; color: #EA7F06; }
            h1 { margin: 6px 0 0; font-size: 22px; font-weight: 600; color: #16255A; }
            p { margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: #64748b; }
            .detail { font-size: 12px; color: #94a3b8; }
            .actions { margin-top: 24px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
            a { display: inline-block; border-radius: 6px; padding: 9px 16px; font-size: 14px; font-weight: 600; text-decoration: none; }
            .primary { background: #16255A; color: #fff; }
            .secondary { border: 1px solid #e5e7eb; color: #334155; }
        </style>
    </head>
    <body>
        <main class="card" role="main">
            <img src="{{ asset('img/company-logo.png') }}" alt="{{ config('app.name', 'HRIS') }}" class="logo">
            @if ($code)<div class="code">GALAT {{ $code }}</div>@endif
            <h1>{{ $friendly[0] }}</h1>
            @if ($friendly[1])<p>{{ $friendly[1] }}</p>@endif
            @if ($message && $friendly[1] && $code !== '404')<p class="detail">{{ $message }}</p>@endif
            <div class="actions">
                <a href="{{ url('/') }}" class="primary">Kembali ke Beranda</a>
                <a href="javascript:history.back()" class="secondary">Halaman sebelumnya</a>
            </div>
        </main>
    </body>
</html>
