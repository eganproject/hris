<x-layouts.app title="Profil Saya - {{ config('app.name', 'HRIS') }}" heading="Profil Saya">
    @php
        $initial = str($employee?->full_name ?: $user->name)->substr(0, 1)->upper();
        $divisions = $employee?->departments->pluck('name')->filter();
        $divisionLabel = $divisions?->isNotEmpty() ? $divisions->join(', ') : ($employee?->department?->name ?? '—');

        // Tiap formulir punya kantong error sendiri. Kalau salah satunya gagal,
        // tabnya dipaksa terbuka — kalau tidak, pesannya tersembunyi di tab lain dan
        // penyimpanannya terlihat seperti diam saja.
        $errorTab = match (true) {
            $errors->updatePassword->isNotEmpty() => 'keamanan',
            $errors->updatePhoto->isNotEmpty() => 'foto',
            $errors->updateProfile->isNotEmpty() => 'pribadi',
            default => null,
        };
    @endphp

    <div class="space-y-6" data-tabs data-tabs-storage-key="profile-tab" @if ($errorTab) data-tabs-initial="{{ $errorTab }}" @endif>
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Akun &amp; data pribadi</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Profil Saya</h1>
                <p class="mt-1 text-sm text-gray-500">Perbarui data pribadi, foto, dan password Anda sendiri.</p>
            </div>
        </section>

        {{-- Kartu identitas. Sengaja gelap seperti kartu "Hari ini" di Dasbor, supaya
             kedua halaman personal itu terbaca sebagai satu keluarga tampilan. --}}
        <section class="overflow-hidden rounded-xl bg-gray-950 text-white shadow-sm">
            <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:gap-6 sm:p-6">
                @if ($employee?->photo_url)
                    <img src="{{ $employee->photo_url }}" alt="Foto profil {{ $employee->full_name }}"
                        class="size-20 flex-none rounded-full object-cover ring-2 ring-white/20">
                @else
                    <span class="flex size-20 flex-none items-center justify-center rounded-full bg-white/10 text-2xl font-semibold text-white/70 ring-2 ring-white/20">{{ $initial }}</span>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="truncate text-xl font-semibold text-white">{{ $employee?->full_name ?? $user->name }}</p>
                    <p class="mt-1 text-sm text-gray-400">
                        {{ $employee?->jobPosition?->name ?? 'Jabatan belum diatur' }}
                        <span class="mx-1 text-gray-600">&middot;</span>
                        {{ $employee?->branch?->name ?? 'Lokasi belum diatur' }}
                    </p>

                    @if ($employee?->motto)
                        <p class="mt-3 border-l-2 border-white/25 pl-3 text-sm italic text-gray-300">&ldquo;{{ $employee->motto }}&rdquo;</p>
                    @elseif ($employee)
                        <p class="mt-3 text-xs text-gray-500">Belum ada motto — tambahkan lewat tab <span class="font-medium text-gray-400">Data Pribadi</span>.</p>
                    @endif
                </div>

                @if ($employee)
                    <dl class="grid flex-none grid-cols-2 gap-x-6 gap-y-3 border-t border-white/10 pt-4 sm:grid-cols-1 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0">
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-gray-500">Nomor Karyawan</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-white">{{ $employee->employee_number ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-gray-500">Bergabung</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-white">{{ $employee->join_date?->translatedFormat('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>
        </section>

        @unless ($employee)
            <section class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Akun ini belum tertaut ke data karyawan, jadi data pribadi dan foto belum bisa diubah. Hubungi HR.
            </section>
        @endunless

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
            <div class="space-y-4">
                @if ($employee)
                    <nav class="grid grid-cols-3 gap-1 rounded-lg border border-gray-200 bg-white p-1 shadow-sm" role="tablist" aria-label="Bagian profil">
                        <button type="button" role="tab" data-tab-button="pribadi" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Data Pribadi</button>
                        <button type="button" role="tab" data-tab-button="foto" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Foto Profil</button>
                        <button type="button" role="tab" data-tab-button="keamanan" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Keamanan</button>
                    </nav>

                    {{-- Data pribadi --}}
                    <section data-tab-panel="pribadi" role="tabpanel" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4">
                            <h2 class="text-base font-semibold text-gray-950">Data Pribadi</h2>
                            <p class="mt-1 text-sm text-gray-500">Hanya kolom di bawah ini yang bisa Anda ubah sendiri. Nama, penempatan, dan kontrak dikelola HR.</p>
                        </div>

                        <form method="POST" action="{{ route('profile.update') }}" data-no-confirm="true" class="space-y-5 p-5">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="motto" class="block text-sm font-medium text-gray-700">Motto</label>
                                <input id="motto" name="motto" maxlength="160" value="{{ old('motto', $employee->motto) }}"
                                    placeholder="mis. Kerja rapi, selesai tepat waktu."
                                    class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <p class="mt-1 text-xs text-gray-400">Satu kalimat singkat, maksimal 160 karakter. Tampil di kartu profil dan halaman detail karyawan Anda.</p>
                                @error('motto', 'updateProfile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Nomor Telepon</label>
                                    <input id="phone" name="phone" value="{{ old('phone', $employee->phone) }}"
                                        class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    @error('phone', 'updateProfile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Alamat</label>
                                    <textarea id="address" name="address" rows="3"
                                        class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('address', $employee->address) }}</textarea>
                                    @error('address', 'updateProfile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="flex justify-end border-t border-gray-100 pt-4">
                                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan Data Pribadi</button>
                            </div>
                        </form>
                    </section>

                    {{-- Foto profil. Markup pratinjaunya sama dengan form karyawan, jadi
                         penangan [data-image-input] di app.js langsung bekerja. --}}
                    <section data-tab-panel="foto" role="tabpanel" hidden class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4">
                            <h2 class="text-base font-semibold text-gray-950">Foto Profil</h2>
                            <p class="mt-1 text-sm text-gray-500">Tampil di daftar karyawan, struktur organisasi, dan kartu profil Anda.</p>
                        </div>

                        <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" data-no-confirm="true" class="p-5" data-image-field data-max-mb="2">
                            @csrf
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                <div class="flex-none">
                                    <img
                                        @if ($employee->photo_url) src="{{ $employee->photo_url }}" @else hidden @endif
                                        alt="Foto profil {{ $employee->full_name }}"
                                        data-image-preview
                                        class="size-24 rounded-full border border-gray-200 object-cover">
                                    <div
                                        data-image-placeholder
                                        @if ($employee->photo_url) hidden @endif
                                        class="flex size-24 items-center justify-center rounded-full border border-dashed border-gray-300 bg-gray-50 text-2xl font-semibold text-gray-400">
                                        {{ $initial }}
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <label for="photo" class="block text-sm font-medium text-gray-700">Pilih foto baru</label>
                                    <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-input data-file-guard data-max-mb="2" data-file-label="Foto profil" required
                                        class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-xs outline-none file:mr-3 file:rounded-sm file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    <p class="mt-2 text-xs text-gray-500">Format JPG, PNG, atau WebP. Maksimal 2 MB. Resolusi minimal 300x300 px dan maksimal 3000x3000 px.</p>
                                    @error('photo', 'updatePhoto')<p data-upload-error data-upload-error-for="photo" class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                    <p data-image-error class="mt-2 hidden text-sm text-red-600"></p>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
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

                {{-- Keamanan. Selalu tersedia: mengganti password tidak butuh data karyawan. --}}
                <section @if ($employee) data-tab-panel="keamanan" role="tabpanel" hidden @endif class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-950">Ubah Password</h2>
                        <p class="mt-1 text-sm text-gray-500">Gunakan password yang berbeda dari yang Anda pakai di layanan lain.</p>
                    </div>

                    <form method="POST" action="{{ route('profile.password') }}" data-no-confirm="true" class="space-y-5 p-5">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Password Saat Ini <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                                    class="mt-2 block w-full max-w-sm rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @error('current_password', 'updatePassword')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password Baru <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                <input id="password" name="password" type="password" required autocomplete="new-password"
                                    class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <p class="mt-1 text-xs text-gray-400">Minimal 8 karakter.</p>
                                @error('password', 'updatePassword')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Ulangi Password Baru <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                    class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-gray-100 pt-4">
                            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Ubah Password</button>
                        </div>
                    </form>
                </section>
            </div>

            {{-- Ringkasan yang hanya bisa diubah HR. --}}
            <aside class="space-y-4">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-950">Akun</h2>
                    </div>
                    <dl class="divide-y divide-gray-50 px-5 text-sm">
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-gray-500">Nama</dt>
                            <dd class="text-right font-medium text-gray-900">{{ $user->name }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-gray-500">Email login</dt>
                            <dd class="min-w-0 break-all text-right font-medium text-gray-900">{{ $user->email ?? '—' }}</dd>
                        </div>
                    </dl>
                    <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">Nama &amp; email login dikelola HR.</p>
                </section>

                @if ($employee)
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4">
                            <h2 class="text-base font-semibold text-gray-950">Data Kepegawaian</h2>
                        </div>
                        <dl class="divide-y divide-gray-50 px-5 text-sm">
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-gray-500">Status</dt>
                                <dd><x-status-badge :tone="$employee->employment_status_tone">{{ $employee->employment_status_label }}</x-status-badge></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-gray-500">Lokasi Kerja</dt>
                                <dd class="text-right font-medium text-gray-900">{{ $employee->branch?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-gray-500">Divisi</dt>
                                <dd class="text-right font-medium text-gray-900">{{ $divisionLabel }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-gray-500">Jabatan</dt>
                                <dd class="text-right font-medium text-gray-900">{{ $employee->jobPosition?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-gray-500">Kontrak</dt>
                                <dd class="text-right font-medium text-gray-900">{{ $employee->currentContract?->contract_type ?? 'Belum ada kontrak aktif' }}</dd>
                            </div>
                        </dl>
                        <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">Penempatan &amp; kontrak dikelola HR.</p>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.app>
