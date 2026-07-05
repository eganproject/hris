<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Cahaya Optima Karya') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f7f8fa] font-sans text-gray-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-6 py-12">
            <section class="w-full max-w-xl rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
                <div class="mx-auto flex size-12 items-center justify-center rounded-md bg-primary text-sm font-semibold text-white">
                    COK
                </div>
                <h1 class="mt-6 text-2xl font-semibold text-gray-950">Cahaya Optima Karya</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500">
                    HRIS administration workspace for people operations.
                </p>
                <div class="mt-8">
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                        Login
                    </a>
                </div>
            </section>
        </main>
    </body>
</html>
