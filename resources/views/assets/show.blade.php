<x-layouts.app title="{{ $asset->asset_code }} - {{ config('app.name', 'HRIS') }}" heading="Detail Aset">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <p class="font-mono text-sm text-gray-500">{{ $asset->asset_code }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">{{ $asset->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-status-badge :tone="$asset->status_tone">{{ $asset->status_label }}</x-status-badge>
                    <x-status-badge :tone="$asset->condition_tone">{{ $asset->condition_label }}</x-status-badge>
                    <span class="text-sm text-gray-500">{{ $asset->category?->name }}</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('assets.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
                @can('assets.update')
                    @unless ($asset->status?->isClosed())
                        <a href="{{ route('assets.edit', $asset) }}" class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover"><x-icon name="pencil"/> Edit</a>
                    @endunless
                @endcan
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-900">Identitas</h2>
                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Merek / Model</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ collect([$asset->brand, $asset->model])->filter()->implode(' ') ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Nomor Seri</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-900">{{ $asset->serial_number ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Lokasi Pemilik</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $asset->owningBranch?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Lokasi Sekarang</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $asset->currentBranch?->name ?? '-' }}
                            @if ($asset->owning_branch_id !== $asset->current_branch_id)
                                <span class="ml-1 text-xs text-amber-600">(di luar cabang pemilik)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tempat Penyimpanan</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if ($asset->storageLocation)
                                {{ $asset->storageLocation->full_path }}
                            @elseif ($asset->status === \App\Enums\AssetStatus::Available)
                                <span class="text-amber-600">Belum diisi</span>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Divisi Pemilik</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $asset->departments->pluck('name')->implode(', ') ?: ($asset->department?->name ?? '-') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Didaftarkan Oleh</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $asset->creator?->name ?? '-' }} <span class="text-gray-400">· {{ $asset->created_at?->format('d/m/Y') }}</span></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Spesifikasi</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $asset->specification ?: '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Catatan</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $asset->notes ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Perolehan &amp; Garansi</h2>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tanggal Perolehan</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $asset->acquired_at?->format('d M Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Nilai Perolehan</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $asset->acquisition_cost !== null ? 'Rp '.number_format((float) $asset->acquisition_cost, 0, ',', '.') : '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Garansi Berakhir</dt>
                        <dd class="mt-1 text-sm">
                            @if ($asset->warranty_expires_at)
                                <span @class(['text-gray-900', 'text-red-600 font-medium' => $asset->warranty_is_expired, 'text-amber-600 font-medium' => $asset->warranty_is_expiring])>{{ $asset->warranty_expires_at->format('d M Y') }}</span>
                                @if ($asset->warranty_is_expired)
                                    <span class="mt-0.5 block text-xs text-red-600">Sudah berakhir</span>
                                @elseif ($asset->warranty_is_expiring)
                                    <span class="mt-0.5 block text-xs text-amber-600">Berakhir dalam {{ $asset->warranty_expires_at->diffInDays(today()) }} hari</span>
                                @endif
                            @else
                                <span class="text-gray-900">-</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Umur Ekonomis</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $asset->category?->useful_life_label ?? '-' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Berkas Aset</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Faktur, kartu garansi, foto kondisi, dan berita acara. Berkas disimpan privat — hanya bisa dibuka lewat halaman ini.</p>
                </div>
                <span class="text-sm text-gray-500">{{ $asset->documents->count() }} berkas</span>
            </div>

            @can('assets.update')
                <form method="POST" action="{{ route('assets.documents.store', $asset) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 border-b border-gray-200 bg-gray-50 px-6 py-5 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Jenis Berkas <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <select id="type" name="type" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @foreach (\App\Models\AssetDocument::TYPE_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Keterangan</label>
                        <input id="title" name="title" value="{{ old('title') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Faktur pembelian Juni 2026">
                        @error('title')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-attachment-field
                        name="file"
                        label="Berkas"
                        :max-mb="\App\Models\AssetDocument::MAX_MB"
                        required
                        hint="Gambar (JPG, PNG, WEBP) atau PDF, maksimal {{ \App\Models\AssetDocument::MAX_MB }} MB."
                    />
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Unggah Berkas</button>
                    </div>
                </form>
            @endcan

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Berkas</th>
                            <th>Jenis</th>
                            <th>Diunggah</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($asset->documents as $document)
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-950">{{ $document->title ?: $document->original_name }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $document->original_name }} · {{ $document->sizeLabel() }}</p>
                                </td>
                                <td>{{ $document->type_label }}</td>
                                <td>
                                    <p class="text-sm text-gray-900">{{ $document->uploader?->name ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $document->created_at?->format('d/m/Y H:i') }}</p>
                                </td>
                                <td class="text-right">
                                    <x-action-menu>
                                        <a href="{{ route('assets.documents.show', [$asset, $document]) }}" target="_blank" rel="noopener" class="action-menu-item"><x-icon name="eye"/> Buka</a>
                                        <a href="{{ route('assets.documents.show', [$asset, $document]) }}?download=1" class="action-menu-item"><x-icon name="download"/> Unduh</a>
                                        @can('assets.update')
                                            <form method="POST" action="{{ route('assets.documents.destroy', [$asset, $document]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="trash"/> Hapus</button>
                                            </form>
                                        @endcan
                                    </x-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="cell-empty">Belum ada berkas untuk aset ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
