<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'HRIS') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f7f8fa] font-sans text-gray-900 antialiased">
        <main class="grid min-h-screen grid-cols-1 lg:grid-cols-[1fr_520px]">
            <section class="hidden bg-[#edf2f7] px-12 py-10 lg:flex lg:flex-col lg:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-md bg-primary text-sm font-semibold text-white">
                        HR
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-950">{{ config('app.name', 'HRIS') }}</p>
                        <p class="text-xs text-gray-500">People operations workspace</p>
                    </div>
                </div>

                <div class="max-w-xl">
                    <p class="text-sm font-medium uppercase text-gray-500">Secure access</p>
                    <h1 class="mt-4 text-4xl font-semibold leading-tight text-gray-950">
                        Kelola data HR dari satu workspace yang rapi.
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-gray-600">
                        Masuk untuk mengelola karyawan, departemen, cabang, shift, dan data absensi dengan antarmuka admin yang tenang.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg border border-white/70 bg-white/70 p-4">
                        <p class="font-semibold text-gray-950">Protected</p>
                        <p class="mt-1 text-xs text-gray-500">Session based auth</p>
                    </div>
                    <div class="rounded-lg border border-white/70 bg-white/70 p-4">
                        <p class="font-semibold text-gray-950">Throttled</p>
                        <p class="mt-1 text-xs text-gray-500">Login rate limits</p>
                    </div>
                    <div class="rounded-lg border border-white/70 bg-white/70 p-4">
                        <p class="font-semibold text-gray-950">Clean</p>
                        <p class="mt-1 text-xs text-gray-500">ERP style panel</p>
                    </div>
                </div>
            </section>

            <section class="flex min-h-screen items-center justify-center px-6 py-10 sm:px-10">
                {{ $slot }}
            </section>
        </main>
    </body>
</html>
