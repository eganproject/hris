<x-layouts.app title="Aplikasi Android - {{ config('app.name', 'HRIS') }}" heading="Aplikasi Android">
    <div class="mx-auto max-w-5xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">System · khusus superadmin</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Aplikasi Android</h1>
            <p class="mt-1 text-sm text-gray-500">APK disimpan di server HRIS ini. Aplikasi HP karyawan bisa diunduh siapa saja dari halaman publik; APK tablet absensi hanya bisa diunduh dari halaman ini.</p>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @foreach ($apps as $key => $app)
            @php $latest = $app['latest']; @endphp
            <section id="app-{{ $key }}" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-start">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-950">{{ $app['label'] }}</h2>
                        @if ($key === \App\Models\AppRelease::EMPLOYEE)
                            <p class="mt-0.5 text-sm text-gray-500">Bagikan halaman unduh ke karyawan:
                                <a href="{{ $publicPageUrl }}" target="_blank" class="font-medium text-gray-900 underline">{{ $publicPageUrl }}</a>
                            </p>
                        @else
                            <p class="mt-0.5 text-sm text-gray-500">Unduh lalu pasang di tablet kantor. Langkah memasangkan tablet ada di <a href="{{ route('attendance.tablets.index') }}" class="font-medium text-gray-900 underline">Tablet Absensi Wajah</a>.</p>
                        @endif
                        @if ($latest)
                            <p class="mt-2 text-sm text-gray-700">Versi terbaru: <span class="font-semibold">{{ $latest->version_name }}</span> <span class="text-gray-400">(build {{ $latest->version_code }})</span> · {{ $latest->sizeLabel() }}</p>
                        @else
                            <p class="mt-2 text-sm text-amber-700">Belum ada APK yang diunggah.</p>
                        @endif
                    </div>
                    @if ($latest)
                        <a href="{{ route('android-apps.download', $latest) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                            Unduh APK
                        </a>
                    @endif
                </div>

                <form method="POST" action="{{ route('android-apps.store') }}" enctype="multipart/form-data" data-no-confirm="true" class="grid grid-cols-1 gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    <input type="hidden" name="app" value="{{ $key }}">
                    <div>
                        <label for="apk-{{ $key }}" class="block text-xs font-medium text-gray-600">Unggah APK baru</label>
                        <input id="apk-{{ $key }}" type="file" name="apk" accept=".apk,application/vnd.android.package-archive" required class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:shadow-xs file:ring-1 file:ring-gray-300">
                    </div>
                    <div>
                        <label for="notes-{{ $key }}" class="block text-xs font-medium text-gray-600">Catatan</label>
                        <input id="notes-{{ $key }}" name="notes" maxlength="1000" placeholder="Mis. perbaikan login" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-xs hover:bg-gray-50">Unggah</button>
                    <p class="text-xs text-gray-500 sm:col-span-3">Versi dibaca otomatis dari APK. APK aplikasi lain ditolak. Batas unggah server saat ini {{ $maxUploadMb }} MB.</p>
                </form>

                @if ($app['releases']->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500">
                                <tr>
                                    <th class="px-5 py-2">Versi</th>
                                    <th class="px-5 py-2">Ukuran</th>
                                    <th class="px-5 py-2">Diunggah</th>
                                    <th class="px-5 py-2">Catatan</th>
                                    <th class="px-5 py-2 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($app['releases'] as $release)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-2.5 font-medium text-gray-900">
                                            {{ $release->version_name }} <span class="text-xs font-normal text-gray-400">build {{ $release->version_code }}</span>
                                            @if ($latest?->is($release))
                                                <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">Terbaru</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-2.5 text-gray-600">{{ $release->sizeLabel() }}</td>
                                        <td class="whitespace-nowrap px-5 py-2.5 text-gray-600">{{ $release->created_at->translatedFormat('j M Y H:i') }} · {{ $release->source === 'ci' ? 'GitHub Actions' : ($release->uploader?->name ?? '-') }}</td>
                                        <td class="px-5 py-2.5 text-gray-600">{{ $release->notes ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-5 py-2.5 text-right">
                                            <a href="{{ route('android-apps.download', $release) }}" class="font-medium text-gray-900 underline">Unduh</a>
                                            <form method="POST" action="{{ route('android-apps.destroy', $release) }}" class="ml-3 inline" data-confirm-message="Hapus APK versi {{ $release->version_name }}? Berkasnya ikut terhapus dari server.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 hover:text-red-700">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.app>
