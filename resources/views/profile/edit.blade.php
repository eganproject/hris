<x-layouts.app title="Profil Saya - {{ config('app.name', 'HRIS') }}" heading="Profil Saya">
    <div class="mx-auto max-w-3xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Akun & data pribadi</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Profil Saya</h1>
        </section>

        {{-- Akun --}}
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-950">Akun</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium text-gray-500">Nama</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $user->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Email (untuk login)</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $user->email ?? '—' }}</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-400">Nama & email login dikelola oleh HR. Hubungi HR bila perlu diubah.</p>
        </section>

        {{-- Foto profil --}}
        @if ($employee)
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-950">Foto Profil</h2>
                <p class="mt-1 text-sm text-gray-500">Foto ini tampil di daftar karyawan, struktur organisasi, dan halaman Anda sendiri.</p>

                @if (session('status') === 'photo-updated')
                    <p class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Foto profil berhasil diperbarui.</p>
                @elseif (session('status') === 'photo-removed')
                    <p class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Foto profil dihapus.</p>
                @endif

                {{-- Markup pratinjaunya sama dengan form karyawan, jadi penangan
                     [data-image-input] di app.js langsung bekerja tanpa skrip baru. --}}
                <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" data-no-confirm="true" class="mt-4" data-image-field data-max-mb="2">
                    @csrf
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <img
                            @if ($employee->photo_url) src="{{ $employee->photo_url }}" @else hidden @endif
                            alt="Foto profil {{ $employee->full_name }}"
                            data-image-preview
                            class="size-24 rounded-full border border-gray-200 object-cover">
                        <div
                            data-image-placeholder
                            @if ($employee->photo_url) hidden @endif
                            class="flex size-24 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-2xl font-semibold text-gray-400">
                            {{ str($employee->full_name ?? '?')->substr(0, 1)->upper() }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <label for="photo" class="sr-only">Pilih foto baru</label>
                            <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-input data-file-guard data-max-mb="2" data-file-label="Foto profil" required
                                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none file:mr-3 file:rounded-sm file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <p class="mt-2 text-xs text-gray-500">Format JPG, PNG, atau WebP. Maksimal 2 MB. Resolusi minimal 300x300 px dan maksimal 3000x3000 px.</p>
                            @error('photo', 'updatePhoto')<p data-upload-error data-upload-error-for="photo" class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            <p data-image-error class="mt-2 hidden text-sm text-red-600"></p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        @if ($employee->photo_path)
                            <button type="submit" form="remove-photo" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50">Hapus Foto</button>
                        @endif
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Foto</button>
                    </div>
                </form>

                @if ($employee->photo_path)
                    <form id="remove-photo" method="POST" action="{{ route('profile.photo.destroy') }}" onsubmit="return confirm('Hapus foto profil Anda?')" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endif
            </section>
        @endif

        {{-- Data karyawan --}}
        @if ($employee)
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-950">Data Karyawan</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><p class="text-xs font-medium text-gray-500">Nomor Karyawan</p><p class="mt-1 text-sm text-gray-900">{{ $employee->employee_number }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Status</p><p class="mt-1 text-sm text-gray-900">{{ $employee->employment_status_label }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Lokasi</p><p class="mt-1 text-sm text-gray-900">{{ $employee->branch?->name ?? '—' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Divisi</p><p class="mt-1 text-sm text-gray-900">{{ $employee->department?->name ?? '—' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Jabatan</p><p class="mt-1 text-sm text-gray-900">{{ $employee->jobPosition?->name ?? '—' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Tanggal Bergabung</p><p class="mt-1 text-sm text-gray-900">{{ $employee->join_date?->translatedFormat('d M Y') ?? '—' }}</p></div>
                </div>

                <hr class="my-5 border-gray-100">

                <form method="POST" action="{{ route('profile.update') }}" data-no-confirm="true" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm font-medium text-gray-700">Data pribadi yang bisa Anda ubah:</p>
                    @if (session('status') === 'profile-updated')
                        <p class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Data pribadi berhasil diperbarui.</p>
                    @endif
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Telepon</label>
                            <input id="phone" name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @error('phone', 'updateProfile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700">Alamat</label>
                            <textarea id="address" name="address" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('address', $employee->address) }}</textarea>
                            @error('address', 'updateProfile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Data Pribadi</button>
                    </div>
                </form>
            </section>
        @else
            <section class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Akun ini belum tertaut ke data karyawan.
            </section>
        @endif

        {{-- Ubah password --}}
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-950">Ubah Password</h2>
            @if (session('status') === 'password-updated')
                <p class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Password berhasil diubah.</p>
            @endif
            <form method="POST" action="{{ route('profile.password') }}" data-no-confirm="true" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Password Saat Ini <span class="field-requirement is-required">*</span></label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="mt-2 block w-full max-w-sm rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @error('current_password', 'updatePassword')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password Baru <span class="field-requirement is-required">*</span></label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 block w-full max-w-sm rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <p class="mt-1 text-xs text-gray-400">Minimal 8 karakter.</p>
                    @error('password', 'updatePassword')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Ulangi Password Baru <span class="field-requirement is-required">*</span></label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block w-full max-w-sm rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Ubah Password</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
