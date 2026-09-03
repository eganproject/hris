<x-layouts.app title="Absensi Saya - {{ config('app.name', 'HRIS') }}" heading="Absensi Saya">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Self-service</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Absensi Saya</h1>
                <p class="mt-1 text-sm text-gray-500">Riwayat absensi 30 hari terakhir. Ajukan koreksi bila ada jam yang salah/terlewat.</p>
            </div>
            <button type="button" data-open-correction class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Ajukan Koreksi</button>
        </section>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if ($errors->selfie->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->selfie->all() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Absen mandiri: hanya muncul saat shift yang sedang berjalan WFH atau dinas
             luar. Untuk shift lintas tengah malam, shift itu bisa saja milik tanggal
             kemarin — panelnya tetap terbuka sampai absen pulang tercatat. --}}
        @if ($remoteStatus)
            @php
                $inLabel = $remoteAttendance?->clock_in?->format('H:i');
                $outLabel = $remoteAttendance?->clock_out?->format('H:i');
                $inProof = $remoteAttendance?->selfieFor('in');
                $outProof = $remoteAttendance?->selfieFor('out');
                $isOvernight = ! $remoteWorkDate->isSameDay(now());
            @endphp
            <section class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-emerald-900">{{ $remoteStatus->label() }} — {{ $remoteWorkDate->translatedFormat('l, d M Y') }}</p>
                        @if ($isOvernight)
                            <p class="mt-0.5 text-xs font-medium text-emerald-800">Shift lintas tengah malam — masih berjalan dari tanggal tersebut.</p>
                        @endif
                        <p class="mt-0.5 text-sm text-emerald-700">
                            Absen masuk: <span class="font-semibold">{{ $inLabel ?? 'belum' }}</span>
                            · Absen pulang: <span class="font-semibold">{{ $outLabel ?? 'belum' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-emerald-700/80">Absen memerlukan foto selfie dan izin lokasi di browser Anda.</p>

                        @if ($inProof || $outProof)
                            <div class="mt-3 flex gap-3">
                                @foreach (['Masuk' => $inProof, 'Pulang' => $outProof] as $label => $proof)
                                    @if ($proof)
                                        <figure class="w-24">
                                            <img src="{{ $proof['photo_url'] }}" alt="Selfie absen {{ strtolower($label) }}" class="h-24 w-24 rounded-md object-cover ring-1 ring-emerald-200">
                                            <figcaption class="mt-1 text-center text-[11px] text-emerald-700">
                                                {{ $label }}
                                                @if ($proof['map_url'])
                                                    · <a href="{{ $proof['map_url'] }}" target="_blank" rel="noopener" class="underline hover:text-emerald-900">peta</a>
                                                @endif
                                            </figcaption>
                                        </figure>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-none items-center gap-2">
                        <button type="button" data-selfie-open="in" data-action="{{ route('my-attendance.check-in') }}" @disabled($inLabel) class="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300">Absen Masuk</button>
                        <button type="button" data-selfie-open="out" data-action="{{ route('my-attendance.check-out') }}" @disabled(! $inLabel || $outLabel) class="rounded-md border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">Absen Pulang</button>
                    </div>
                </div>
            </section>
        @endif

        {{-- Mode uji coba superadmin: memastikan kamera & lokasi berfungsi tanpa
             menyentuh data absensi sama sekali --}}
        @if ($selfieTestMode)
            @php $test = session('selfie_test'); @endphp
            <section class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="inline-flex items-center gap-1.5 rounded-md bg-gray-200 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-700">Mode Uji Coba</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950">Absen Selfie — {{ now()->translatedFormat('l, d M Y') }}</p>
                        <p class="mt-0.5 text-sm text-gray-600">Hari ini bukan hari WFH atau dinas luar. Anda bisa mencoba kamera dan pembacaan lokasi di sini; <span class="font-medium">hasilnya tidak disimpan sebagai absensi.</span></p>

                        @if ($test)
                            <div class="mt-3 flex items-start gap-3 rounded-md border border-gray-200 bg-white p-3">
                                <img src="{{ $test['photo_url'] }}" alt="Hasil uji foto selfie" class="size-20 rounded object-cover ring-1 ring-gray-200">
                                <dl class="text-xs text-gray-600">
                                    <div class="flex gap-1"><dt class="font-medium text-gray-700">Foto:</dt><dd>berhasil ({{ $test['photo_kb'] }} KB)</dd></div>
                                    <div class="mt-0.5 flex gap-1">
                                        <dt class="font-medium text-gray-700">Lokasi:</dt>
                                        <dd>
                                            {{ number_format($test['latitude'], 5) }}, {{ number_format($test['longitude'], 5) }}
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ $test['latitude'] }},{{ $test['longitude'] }}" target="_blank" rel="noopener" class="ml-1 underline hover:text-gray-900">lihat peta</a>
                                        </dd>
                                    </div>
                                    <div class="mt-0.5 flex gap-1"><dt class="font-medium text-gray-700">Akurasi:</dt><dd>{{ $test['accuracy'] !== null ? '±'.$test['accuracy'].' m' : 'tidak dilaporkan' }}</dd></div>
                                    <div class="mt-0.5 flex gap-1"><dt class="font-medium text-gray-700">Diuji pukul:</dt><dd>{{ $test['tested_at'] }}</dd></div>
                                </dl>
                            </div>
                        @endif
                    </div>
                    <div class="flex-none">
                        <button type="button" data-selfie-open="test" data-action="{{ route('my-attendance.selfie-test') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-100">Coba Absen Selfie</button>
                    </div>
                </div>
            </section>
        @endif

        {{-- Attendance history --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Riwayat Absensi</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th><th>Status</th><th>Bukti</th></tr></thead>
                    <tbody>
                        @forelse ($attendances as $att)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $att->work_date->translatedFormat('D, d M Y') }}</td>
                                <td class="text-sm text-gray-600">{{ $att->shift?->code ?? '—' }}</td>
                                <td class="text-sm {{ $att->late_minutes > 0 ? 'font-medium text-amber-600' : 'text-gray-700' }}">{{ $att->clock_in_label }}</td>
                                <td class="text-sm text-gray-700">{{ $att->clock_out_label }}</td>
                                <td class="text-sm text-gray-600">{{ $att->late_minutes > 0 ? $att->late_minutes.'m' : '—' }}</td>
                                <td class="text-sm text-gray-600">{{ $att->overtime_minutes > 0 ? floor($att->overtime_minutes / 60).'j '.($att->overtime_minutes % 60).'m' : '—' }}</td>
                                <td><x-status-badge :tone="$att->status->tone()">{{ $att->status->label() }}</x-status-badge></td>
                                <td><x-attendance-proof :attendance="$att" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="cell-empty">Belum ada data absensi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- My corrections --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-3"><h2 class="text-sm font-semibold text-gray-950">Pengajuan Koreksi Saya</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Usulan Jam</th><th>Alasan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($corrections as $c)
                            <tr>
                                <td class="text-sm text-gray-700">{{ $c->work_date->translatedFormat('d M Y') }}</td>
                                <td class="text-sm text-gray-700">{{ $c->requested_clock_in ?? '—' }} / {{ $c->requested_clock_out ?? '—' }}</td>
                                <td class="max-w-xs truncate text-sm text-gray-600" title="{{ $c->reason }}">{{ $c->reason }}</td>
                                <td>
                                    <x-status-badge :tone="$c->status_tone">{{ $c->status_label }}</x-status-badge>
                                    @if ($c->status === 'rejected' && $c->decision_notes)<p class="mt-1 text-xs text-gray-400">{{ $c->decision_notes }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @if ($c->isPending())
                                        <form method="POST" action="{{ route('my-attendance.corrections.cancel', $c) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:text-red-700">Batalkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cell-empty">Belum ada pengajuan koreksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if ($remoteStatus || $selfieTestMode)
        <dialog id="selfie-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
            <form method="POST" action="" enctype="multipart/form-data" data-no-confirm="true" data-selfie-form class="space-y-4 p-6">
                @csrf
                <div>
                    <h3 class="text-base font-semibold text-gray-950" data-selfie-title>Absen Masuk</h3>
                    <p class="mt-1 text-sm text-gray-500" data-selfie-subtitle>Ambil foto selfie. Lokasi Anda ikut terkirim sebagai bukti kehadiran.</p>
                </div>

                {{-- Preview kamera; berganti ke hasil jepretan setelah foto diambil --}}
                <div class="relative overflow-hidden rounded-md bg-gray-900" style="aspect-ratio: 3 / 4;">
                    <video data-selfie-video autoplay playsinline muted class="h-full w-full object-cover" style="transform: scaleX(-1);"></video>
                    <img data-selfie-preview alt="Hasil foto selfie" class="absolute inset-0 hidden h-full w-full object-cover">
                    <p data-selfie-camera-error class="absolute inset-0 hidden items-center justify-center p-6 text-center text-sm text-white"></p>
                </div>
                <canvas data-selfie-canvas class="hidden"></canvas>

                <p data-selfie-geo class="text-xs text-gray-500">Membaca lokasi…</p>

                <input type="file" name="photo" accept="image/jpeg" data-selfie-file class="hidden">
                <input type="hidden" name="latitude" data-selfie-lat>
                <input type="hidden" name="longitude" data-selfie-lng>
                <input type="hidden" name="accuracy" data-selfie-acc>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-selfie-cancel class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="button" data-selfie-retake class="hidden rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Ulangi</button>
                    <button type="button" data-selfie-capture class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300">Ambil Foto</button>
                    <button type="submit" data-selfie-submit disabled class="hidden rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300">Kirim Absen</button>
                </div>
            </form>
        </dialog>
    @endif

    <dialog id="correction-dialog" class="w-full max-w-md rounded-lg p-0 backdrop:bg-black/40">
        <form method="POST" action="{{ route('my-attendance.corrections.store') }}" data-no-confirm="true" class="space-y-4 p-6">
            @csrf
            <div>
                <h3 class="text-base font-semibold text-gray-950">Ajukan Koreksi Absensi</h3>
                <p class="mt-1 text-sm text-gray-500">Isi jam yang seharusnya. HR akan meninjau pengajuan Anda.</p>
            </div>
            <div>
                <label for="cor-date" class="block text-sm font-medium text-gray-700">Tanggal <span class="field-requirement is-required">*</span></label>
                <input type="date" name="work_date" id="cor-date" max="{{ now()->toDateString() }}" value="{{ old('work_date') }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @error('work_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="cor-in" class="block text-sm font-medium text-gray-700">Jam Masuk</label>
                    <input type="time" name="requested_clock_in" id="cor-in" value="{{ old('requested_clock_in') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="cor-out" class="block text-sm font-medium text-gray-700">Jam Pulang</label>
                    <input type="time" name="requested_clock_out" id="cor-out" value="{{ old('requested_clock_out') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            @error('requested_clock_in')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div>
                <label for="cor-reason" class="block text-sm font-medium text-gray-700">Alasan <span class="field-requirement is-required">*</span></label>
                <textarea name="reason" id="cor-reason" rows="3" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="mis. Lupa tap saat pulang.">{{ old('reason') }}</textarea>
                @error('reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-close-dialog class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Kirim</button>
            </div>
        </form>
    </dialog>

    @push('scripts')
    <script>
        (function () {
            const dialog = document.getElementById('correction-dialog');
            if (!dialog) return;
            document.querySelector('[data-open-correction]')?.addEventListener('click', () => dialog.showModal());
            dialog.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());
            @if ($errors->any()) dialog.showModal(); @endif
        })();
    </script>

    {{-- Absen mandiri: kamera + koordinat. Keduanya butuh HTTPS (atau localhost).
         Hanya dikirim pada hari WFH/dinas luar, sama seperti dialognya. --}}
    @if ($remoteStatus || $selfieTestMode)
    <script>
        (function () {
            const dialog = document.getElementById('selfie-dialog');
            if (!dialog) return;

            const form = dialog.querySelector('[data-selfie-form]');
            const video = dialog.querySelector('[data-selfie-video]');
            const preview = dialog.querySelector('[data-selfie-preview]');
            const canvas = dialog.querySelector('[data-selfie-canvas]');
            const cameraError = dialog.querySelector('[data-selfie-camera-error]');
            const geoNote = dialog.querySelector('[data-selfie-geo]');
            const fileInput = dialog.querySelector('[data-selfie-file]');
            const latInput = dialog.querySelector('[data-selfie-lat]');
            const lngInput = dialog.querySelector('[data-selfie-lng]');
            const accInput = dialog.querySelector('[data-selfie-acc]');
            const title = dialog.querySelector('[data-selfie-title]');
            const subtitle = dialog.querySelector('[data-selfie-subtitle]');
            const captureBtn = dialog.querySelector('[data-selfie-capture]');
            const retakeBtn = dialog.querySelector('[data-selfie-retake]');
            const submitBtn = dialog.querySelector('[data-selfie-submit]');
            const cancelBtn = dialog.querySelector('[data-selfie-cancel]');

            const MAX_EDGE = 720; // sisi terpanjang foto yang disimpan
            const MODES = {
                in: { title: 'Absen Masuk', submit: 'Kirim Absen', subtitle: 'Ambil foto selfie. Lokasi Anda ikut terkirim sebagai bukti kehadiran.' },
                out: { title: 'Absen Pulang', submit: 'Kirim Absen', subtitle: 'Ambil foto selfie. Lokasi Anda ikut terkirim sebagai bukti kehadiran.' },
                test: { title: 'Uji Coba Absen Selfie', submit: 'Jalankan Uji', subtitle: 'Pemeriksaan kamera dan lokasi. Hasilnya tidak disimpan sebagai absensi.' },
            };
            let stream = null;
            let hasPhoto = false;
            let hasCoords = false;

            function syncSubmit() {
                submitBtn.disabled = !(hasPhoto && hasCoords);
            }

            function showCameraError(message) {
                video.classList.add('hidden');
                cameraError.textContent = message;
                cameraError.classList.remove('hidden');
                cameraError.classList.add('flex');
                captureBtn.disabled = true;
            }

            async function startCamera() {
                if (!navigator.mediaDevices?.getUserMedia) {
                    showCameraError('Kamera tidak tersedia. Halaman ini harus dibuka lewat HTTPS agar browser mengizinkan kamera.');
                    return;
                }

                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    video.srcObject = stream;
                    video.classList.remove('hidden');
                    cameraError.classList.add('hidden');
                    cameraError.classList.remove('flex');
                    captureBtn.disabled = false;
                } catch (e) {
                    showCameraError(e.name === 'NotAllowedError'
                        ? 'Akses kamera ditolak. Izinkan kamera di pengaturan browser, lalu buka lagi.'
                        : 'Kamera tidak bisa dibuka: ' + e.message);
                }
            }

            function stopCamera() {
                stream?.getTracks().forEach((track) => track.stop());
                stream = null;
                video.srcObject = null;
            }

            function requestLocation() {
                if (!navigator.geolocation) {
                    geoNote.textContent = 'Perangkat ini tidak mendukung berbagi lokasi.';
                    geoNote.className = 'text-xs text-red-600';
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        const { latitude, longitude, accuracy } = pos.coords;
                        latInput.value = latitude;
                        lngInput.value = longitude;
                        accInput.value = accuracy != null ? Math.round(accuracy) : '';
                        hasCoords = true;
                        syncSubmit();
                        geoNote.textContent = 'Lokasi terbaca: ' + latitude.toFixed(5) + ', ' + longitude.toFixed(5)
                            + (accuracy != null ? ' (±' + Math.round(accuracy) + ' m)' : '');
                        geoNote.className = 'text-xs text-emerald-700';
                    },
                    (err) => {
                        hasCoords = false;
                        syncSubmit();
                        geoNote.textContent = err.code === err.PERMISSION_DENIED
                            ? 'Akses lokasi ditolak. Izinkan lokasi di browser, lalu buka lagi dialog ini.'
                            : 'Lokasi belum terbaca: ' + err.message;
                        geoNote.className = 'text-xs text-red-600';
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
                );
            }

            function capture() {
                const w = video.videoWidth;
                const h = video.videoHeight;
                if (!w || !h) return;

                const scale = Math.min(1, MAX_EDGE / Math.max(w, h));
                canvas.width = Math.round(w * scale);
                canvas.height = Math.round(h * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                canvas.toBlob((blob) => {
                    if (!blob) return;

                    // Input file diisi lewat DataTransfer supaya form terkirim sebagai
                    // multipart biasa — tidak perlu fetch, flash message tetap jalan.
                    const transfer = new DataTransfer();
                    transfer.items.add(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
                    fileInput.files = transfer.files;

                    preview.src = canvas.toDataURL('image/jpeg');
                    preview.classList.remove('hidden');
                    video.classList.add('hidden');
                    stopCamera();

                    hasPhoto = true;
                    syncSubmit();
                    captureBtn.classList.add('hidden');
                    retakeBtn.classList.remove('hidden');
                    submitBtn.classList.remove('hidden');
                }, 'image/jpeg', 0.7);
            }

            function reset() {
                hasPhoto = false;
                fileInput.value = '';
                preview.classList.add('hidden');
                preview.removeAttribute('src');
                video.classList.remove('hidden');
                captureBtn.classList.remove('hidden');
                captureBtn.disabled = true;
                retakeBtn.classList.add('hidden');
                submitBtn.classList.add('hidden');
                syncSubmit();
            }

            function close() {
                stopCamera();
                dialog.close();
            }

            document.querySelectorAll('[data-selfie-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    const mode = MODES[button.dataset.selfieOpen] ?? MODES.in;
                    form.action = button.dataset.action;
                    title.textContent = mode.title;
                    subtitle.textContent = mode.subtitle;
                    submitBtn.textContent = mode.submit;

                    hasCoords = false;
                    geoNote.textContent = 'Membaca lokasi…';
                    geoNote.className = 'text-xs text-gray-500';
                    reset();

                    dialog.showModal();
                    startCamera();
                    requestLocation();
                });
            });

            captureBtn.addEventListener('click', capture);
            retakeBtn.addEventListener('click', () => { reset(); startCamera(); });
            cancelBtn.addEventListener('click', close);
            dialog.addEventListener('cancel', stopCamera); // tombol Esc
            form.addEventListener('submit', () => {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Mengirim…';
                stopCamera();
            });
        })();
    </script>
    @endif
    @endpush
</x-layouts.app>
