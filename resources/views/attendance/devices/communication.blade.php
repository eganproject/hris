<x-layouts.app title="Detail Log Komunikasi - {{ config('app.name', 'HRIS') }}" heading="Detail Log Komunikasi">
    @php
        $device = $communication->device;
        $terurai = collect($lines)->filter(fn (array $line) => $line['parsed'] !== null);
        $tercatat = $terurai->filter(fn (array $line) => $line['punch'] !== null);
    @endphp

    <div class="mx-auto max-w-5xl space-y-5">
        <section>
            <a href="{{ route('attendance.devices.monitor') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-900">
                <span aria-hidden="true">&larr;</span> Monitor Mesin
            </a>
            <h1 class="mt-2 text-2xl font-semibold text-gray-950">Kiriman {{ $communication->event_label }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $device?->name ?? 'Perangkat tidak ditemukan' }}
                <span class="mx-1 text-gray-300" aria-hidden="true">&middot;</span>
                {{ $communication->created_at?->translatedFormat('d M Y, H:i:s') }}
            </p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Ringkasan</h2>
            </div>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Peristiwa</dt>
                    <dd class="mt-1"><x-status-badge :tone="$communication->event_tone">{{ $communication->event_label }}</x-status-badge></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Mesin</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $device?->name ?? '—' }}</dd>
                    <dd class="mt-0.5 font-mono text-xs text-gray-500">{{ $device?->serial_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Alamat IP</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-900">{{ $communication->ip ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Record Dilaporkan</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $communication->event === 'attlog' ? $communication->records_count.' punch baru' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Ukuran Kiriman</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $communication->payloadSizeLabel() ?? 'Tanpa isi' }}</dd>
                    @if ($communication->isTruncated())
                        <dd class="mt-0.5 text-xs text-amber-600">Dipangkas — hanya bagian awalnya yang disimpan.</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Waktu Diterima</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $communication->created_at?->translatedFormat('d M Y, H:i:s') }}</dd>
                </div>
            </dl>
        </section>

        @if ($lines)
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-1 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950">Baris Kiriman</h2>
                        <p class="mt-1 text-sm text-gray-500">Tiap baris dibaca dengan aturan yang sama seperti saat kirimannya diterima.</p>
                    </div>
                    <p class="text-sm text-gray-500">{{ $tercatat->count() }} dari {{ count($lines) }} baris tercatat</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PIN</th>
                                <th>Waktu</th>
                                <th>State</th>
                                <th>Verifikasi</th>
                                <th>Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $line)
                                @php $parsed = $line['parsed']; $punch = $line['punch']; @endphp
                                <tr>
                                    @if (! $parsed)
                                        <td colspan="4" class="font-mono text-xs text-gray-500">{{ $line['raw'] }}</td>
                                        <td><x-status-badge tone="danger">Tidak terbaca</x-status-badge></td>
                                    @else
                                        <td class="font-mono text-sm text-gray-900">{{ $parsed['pin'] }}</td>
                                        <td class="whitespace-nowrap text-sm text-gray-900">{{ $parsed['punched_at']->translatedFormat('d M Y, H:i:s') }}</td>
                                        {{-- State dari mesin ditampilkan apa adanya. Aplikasi ini tidak
                                             memakainya untuk menentukan masuk/pulang — mesin X100-C sering
                                             menandainya sembarang kalau tombolnya tidak ditekan. --}}
                                        <td class="text-sm text-gray-600">{{ $parsed['state'] }} <span class="text-xs text-gray-400">({{ $parsed['state'] === 0 ? 'masuk' : 'pulang' }} menurut mesin)</span></td>
                                        <td class="text-sm text-gray-600">{{ $parsed['verify'] }}</td>
                                        <td>
                                            @if (! $punch)
                                                <x-status-badge tone="warning">Tidak tersimpan</x-status-badge>
                                            @elseif ($punch->status === \App\Models\AttendancePunch::STATUS_MATCHED)
                                                <x-status-badge tone="success">Tercatat</x-status-badge>
                                                <p class="mt-1 text-xs text-gray-500">{{ $punch->employee?->full_name ?? '—' }}</p>
                                            @elseif ($punch->status === \App\Models\AttendancePunch::STATUS_UNMATCHED)
                                                <x-status-badge tone="warning">PIN belum dipetakan</x-status-badge>
                                            @else
                                                <x-status-badge tone="neutral">Diabaikan</x-status-badge>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($tercatat->count() < $terurai->count())
                    <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
                        Baris bertanda <span class="font-medium">Tidak tersimpan</span> tidak menghasilkan punch — biasanya karena PIN atau waktunya tidak terbaca mesin dengan benar. Baris yang sudah pernah dikirim sebelumnya tetap tampil sebagai <span class="font-medium">Tercatat</span>, karena punch-nya memang sudah ada.
                    </p>
                @endif
            </section>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Isi Mentah</h2>
                <p class="mt-1 text-sm text-gray-500">Persis seperti yang dikirim mesin, tanpa diolah.</p>
            </div>
            <div class="px-5 py-4">
                @if ($communication->payload)
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-all rounded-lg bg-gray-50 p-4 font-mono text-xs leading-relaxed text-gray-700">{{ $communication->payload }}</pre>
                @else
                    <p class="py-6 text-center text-sm text-gray-400">
                        Kiriman ini tidak berisi apa-apa, atau isinya sudah dipangkas karena lewat masa simpan.
                    </p>
                @endif
            </div>
        </section>
    </div>
</x-layouts.app>
