<x-layouts.auth title="Login - {{ config('app.name', 'HRIS') }}">
    <div class="w-full max-w-md">
        <div class="mb-8 lg:hidden">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-md bg-primary text-sm font-semibold text-white">
                    HR
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-950">{{ config('app.name', 'HRIS') }}</p>
                    <p class="text-xs text-gray-500">People operations workspace</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <div>
                <h1 class="text-2xl font-semibold text-gray-950">Login</h1>
                <p class="mt-2 text-sm text-gray-500">Masuk ke admin panel HRIS.</p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        required
                        autofocus
                        class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-950 shadow-xs outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20"
                        placeholder="you@company.com"
                    >
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between gap-4">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    </div>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-950 shadow-xs outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20"
                        placeholder="Masukkan password"
                    >
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-3 text-sm text-gray-600">
                    <input
                        name="remember"
                        type="checkbox"
                        value="1"
                        class="size-4 rounded border-gray-300 text-primary focus:ring-primary"
                    >
                    Remember me
                </label>

                <button type="submit" class="flex w-full items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</x-layouts.auth>
