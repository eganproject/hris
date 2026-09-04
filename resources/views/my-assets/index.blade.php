<x-layouts.app title="Aset Saya - {{ config('app.name', 'HRIS') }}" heading="Aset Saya">
    <div class="mx-auto max-w-5xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Self-service</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Aset Saya</h1>
            <p class="mt-1 text-sm text-gray-500">Barang milik perusahaan yang tercatat ada di tangan Anda.</p>
        </section>

        @unless ($employee)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Akun Anda belum terhubung ke data karyawan, jadi belum ada aset yang bisa ditampilkan. Hubungi HR untuk menghubungkannya.
            </div>
        @endunless

        <section class="space-y-4">
            <h2 class="text-sm font-semibold text-gray-900">Sedang Dipegang</h2>

            @forelse ($open as $assignment)
                <article @class(['rounded-lg border bg-white p-5 shadow-sm', 'border-amber-300 ring-1 ring-amber-100' => ! $assignment->isAcknowledged(), 'border-gray-200' => $assignment->isAcknowledged()])>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-950">{{ $assignment->asset?->name }}</p>
                            <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $assignment->asset?->asset_code }} · {{ $assignment->asset?->category?->name }}</p>
                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                <div>
                                    <dt class="text-xs text-gray-500">Diserahkan</dt>
                                    <dd class="text-gray-900">{{ $assignment->assigned_at?->translatedFormat('d M Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">Kondisi saat diterima</dt>
                                    <dd class="text-gray-900">{{ $assignment->condition_out_label }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">Target kembali</dt>
                                    <dd @class(['text-gray-900', 'font-medium text-red-600' => $assignment->isOverdue()])>
                                        {{ $assignment->expected_return_at?->translatedFormat('d M Y') ?? 'Tanpa batas waktu' }}
                                    </dd>
                                </div>
                            </dl>
                            @if ($assignment->purpose)
                                <p class="mt-2 text-sm text-gray-600">Keperluan: {{ $assignment->purpose }}</p>
                            @endif
                        </div>
                        <div class="shrink-0">
                            @if ($assignment->isAcknowledged())
                                <x-status-badge tone="success">Sudah dikonfirmasi</x-status-badge>
                            @else
                                <x-status-badge tone="warning">Belum dikonfirmasi</x-status-badge>
                            @endif
                        </div>
                    </div>

                    @unless ($assignment->isAcknowledged())
                        {{-- Konfirmasi ini yang membuat catatan serah-terima berdiri di atas
                             dua pihak, bukan cuma catatan sepihak petugas. --}}
                        <form method="POST" action="{{ route('my-assets.acknowledge', $assignment) }}" class="mt-4 border-t border-gray-100 pt-4">
                            @csrf
                            <label for="note-{{ $assignment->id }}" class="block text-sm font-medium text-gray-700">Konfirmasi penerimaan</label>
                            <p class="mt-1 text-xs text-gray-500">Tekan tombol di bawah bila barangnya memang sudah Anda terima. Bila ada yang tidak sesuai, tulis di catatan lebih dulu.</p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <input id="note-{{ $assignment->id }}" name="note" maxlength="500" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Catatan (opsional) — mis. charger tidak ikut">
                                <button type="submit" class="shrink-0 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Saya Terima</button>
                            </div>
                            @error('note')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </form>
                    @endunless
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-gray-200 px-5 py-8 text-center text-sm text-gray-400">Tidak ada aset perusahaan yang sedang Anda pegang.</p>
            @endforelse
        </section>

        @if ($history->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Riwayat</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Aset yang pernah Anda pegang dan sudah dikembalikan.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Aset</th>
                                <th>Dipegang</th>
                                <th>Dikembalikan</th>
                                <th>Kondisi Saat Kembali</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $assignment)
                                <tr>
                                    <td>
                                        <p class="font-medium text-gray-950">{{ $assignment->asset?->name }}</p>
                                        <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $assignment->asset?->asset_code }}</p>
                                    </td>
                                    <td>{{ $assignment->assigned_at?->translatedFormat('d M Y') }}</td>
                                    <td>{{ $assignment->returned_at?->translatedFormat('d M Y') }}</td>
                                    <td>{{ $assignment->condition_in_label ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
