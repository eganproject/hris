<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Unduh Aplikasi HP - {{ config('app.name', 'HRIS') }}</title>
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/company-logo-192.png') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-[#f7f8fa] font-sans text-gray-900 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/company-logo.png') }}" alt="" class="size-10 rounded-full bg-white object-contain">
                <div>
                    <p class="text-sm font-semibold text-gray-950">{{ config('app.name', 'HRIS') }}</p>
                    <p class="text-xs text-gray-500">Aplikasi HP karyawan</p>
                </div>
            </div>

            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h1 class="text-xl font-semibold text-gray-950">Unduh aplikasi HP karyawan</h1>
                <p class="mt-2 text-sm text-gray-500">Untuk HP Android 7 ke atas. Masuk dengan akun HRIS (email atau NIK) yang sama dengan web.</p>

                @if ($release)
                    <a href="{{ route('app-download.apk') }}" class="mt-5 flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                        Unduh APK
                    </a>
                    <p class="mt-2 text-center text-xs text-gray-500">Versi {{ $release->version_name }} · {{ $release->sizeLabel() }} · {{ $release->created_at->translatedFormat('j M Y') }}</p>
                    @if ($release->notes)
                        <div class="mt-4 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                            <p class="font-semibold text-gray-800">Yang baru</p>
                            <p class="mt-1 whitespace-pre-line">{{ $release->notes }}</p>
                        </div>
                    @endif
                @else
                    <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Aplikasi belum tersedia. Hubungi HR.</div>
                @endif
            </section>

            <section class="mt-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-950">Cara memasang</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-600">
                    <li>Ketuk <span class="font-medium text-gray-800">Unduh APK</span>. Bila ada peringatan "berkas ini bisa berbahaya", pilih <span class="font-medium text-gray-800">Tetap unduh</span>.</li>
                    <li>Buka berkas yang sudah terunduh. Bila diminta, izinkan browser <span class="font-medium text-gray-800">menginstal aplikasi tidak dikenal</span>, lalu kembali.</li>
                    <li>Ketuk <span class="font-medium text-gray-800">Instal</span>, lalu buka aplikasinya dan masuk dengan akun HRIS.</li>
                    <li>Untuk memperbarui, unduh lagi dari halaman ini dan instal di atas aplikasi lama (data tidak hilang).</li>
                </ol>
            </section>

            <p class="mt-6 text-center text-xs text-gray-400">
                <a href="{{ route('login') }}" class="hover:text-gray-600">Masuk ke web HRIS</a>
            </p>
        </main>
    </body>
</html>
