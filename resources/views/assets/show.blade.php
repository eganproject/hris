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
            <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Pemegang Saat Ini</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Siapa yang sedang bertanggung jawab atas barang ini.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('asset-assignments.assign')
                        @if ($asset->status?->isAssignable())
                            <button type="button" data-panel-toggle="assign" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Serahkan ke Karyawan</button>
                        @endif
                    @endcan
                    @can('asset-assignments.return')
                        @if ($asset->currentAssignment)
                            <button type="button" data-panel-toggle="return" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Terima Kembali</button>
                        @endif
                    @endcan
                    @can('asset-assignments.transfer')
                        @if (! $asset->currentAssignment && ! $asset->status?->isClosed())
                            <button type="button" data-panel-toggle="transfer" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Pindahkan Lokasi</button>
                        @endif
                    @endcan
                </div>
            </div>

            <div class="px-6 py-5">
                @if ($assignment = $asset->currentAssignment)
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Karyawan</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-950">{{ $assignment->employee?->full_name ?? '-' }}</dd>
                                <dd class="mt-0.5 font-mono text-xs text-gray-500">{{ $assignment->employee?->employee_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Sejak</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $assignment->assigned_at?->translatedFormat('d M Y, H:i') }}</dd>
                                <dd class="mt-0.5 text-xs text-gray-500">Diserahkan oleh {{ $assignment->assignedBy?->name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Target Kembali</dt>
                                <dd class="mt-1 text-sm">
                                    @if ($assignment->expected_return_at)
                                        <span @class(['text-gray-900', 'font-medium text-red-600' => $assignment->isOverdue()])>{{ $assignment->expected_return_at->translatedFormat('d M Y') }}</span>
                                        @if ($assignment->isOverdue())
                                            <span class="mt-0.5 block text-xs text-red-600">Sudah lewat {{ $assignment->expected_return_at->diffInDays(today()) }} hari</span>
                                        @endif
                                    @else
                                        <span class="text-gray-500">Tanpa batas waktu</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-3">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Keperluan</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $assignment->purpose ?: '-' }}</dd>
                            </div>
                        </dl>
                        <div class="shrink-0">
                            @if ($assignment->isAcknowledged())
                                <x-status-badge tone="success">Dikonfirmasi {{ $assignment->acknowledged_at->translatedFormat('d M Y') }}</x-status-badge>
                            @else
                                <x-status-badge tone="warning">Menunggu konfirmasi karyawan</x-status-badge>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Aset ini sedang tidak dipegang siapa pun.</p>
                @endif
            </div>
        </section>

        @can('asset-assignments.assign')
            @if ($asset->status?->isAssignable())
                <section data-panel="assign" hidden class="rounded-lg border border-primary/30 bg-white p-6 shadow-sm ring-1 ring-primary/10">
                    <h2 class="text-sm font-semibold text-gray-900">Serahkan ke Karyawan</h2>
                    <p class="mt-1 text-xs text-gray-500">Karyawan yang menerima akan diminta mengonfirmasi penerimaannya lewat menu Aset Saya.</p>
                    <form method="POST" action="{{ route('assets.assign', $asset) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        @csrf
                        <div class="md:col-span-2">
                            <label for="employee_id" class="block text-sm font-medium text-gray-700">Karyawan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="employee_id" name="employee_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Pilih karyawan</option>
                                @foreach ($employees as $person)
                                    <option value="{{ $person->id }}" @selected((string) old('employee_id') === (string) $person->id)>{{ $person->full_name }} — {{ $person->employee_number }}</option>
                                @endforeach
                            </select>
                            @error('employee_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="condition_out" class="block text-sm font-medium text-gray-700">Kondisi Saat Diserahkan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="condition_out" name="condition_out" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @foreach ($serviceableConditions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('condition_out', $asset->condition?->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Aset rusak atau tidak layak tidak boleh diserahkan.</p>
                            @error('condition_out')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="expected_return_at" class="block text-sm font-medium text-gray-700">Target Pengembalian</label>
                            <input id="expected_return_at" name="expected_return_at" type="date" min="{{ today()->format('Y-m-d') }}" value="{{ old('expected_return_at') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <p class="mt-1 text-xs text-gray-500">Kosongkan bila dipegang untuk seterusnya.</p>
                            @error('expected_return_at')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="purpose" class="block text-sm font-medium text-gray-700">Keperluan</label>
                            <input id="purpose" name="purpose" value="{{ old('purpose') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Kerja harian, dinas ke Surabaya, dsb.">
                            @error('purpose')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2">
                            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Serahkan</button>
                            <button type="button" data-panel-close="assign" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        </div>
                    </form>
                </section>
            @endif
        @endcan

        @can('asset-assignments.return')
            @if ($asset->currentAssignment)
                <section data-panel="return" hidden class="rounded-lg border border-primary/30 bg-white p-6 shadow-sm ring-1 ring-primary/10">
                    <h2 class="text-sm font-semibold text-gray-900">Terima Kembali</h2>
                    <p class="mt-1 text-xs text-gray-500">Periksa barangnya dulu — kondisi yang dicatat di sini menentukan status aset berikutnya.</p>
                    <form method="POST" action="{{ route('assets.return', $asset) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        @csrf
                        <div>
                            <label for="condition_in" class="block text-sm font-medium text-gray-700">Kondisi Saat Kembali <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="condition_in" name="condition_in" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @foreach ($conditions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('condition_in', $asset->condition?->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('condition_in')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="next_status" class="block text-sm font-medium text-gray-700">Status Setelah Kembali</label>
                            <select id="next_status" name="next_status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Ikuti kondisi barangnya</option>
                                @foreach ($returnOutcomes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('next_status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Rusak jadi Perawatan, tidak layak jadi Tidak Dipakai. Pilih sendiri bila perlu lain.</p>
                            @error('next_status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="return_notes" class="block text-sm font-medium text-gray-700">Catatan Pemeriksaan</label>
                            <textarea id="return_notes" name="return_notes" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Lecet di sudut kiri, charger lengkap, dsb.">{{ old('return_notes') }}</textarea>
                            @error('return_notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2">
                            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Catat Pengembalian</button>
                            <button type="button" data-panel-close="return" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        </div>
                    </form>
                </section>
            @endif
        @endcan

        @can('asset-assignments.transfer')
            @if (! $asset->currentAssignment && ! $asset->status?->isClosed())
                <section data-panel="transfer" hidden class="rounded-lg border border-primary/30 bg-white p-6 shadow-sm ring-1 ring-primary/10">
                    <h2 class="text-sm font-semibold text-gray-900">Pindahkan Lokasi</h2>
                    <p class="mt-1 text-xs text-gray-500">Yang berpindah adalah tempat barangnya berada. Cabang pemiliknya, dan kode asetnya, tidak ikut berubah.</p>
                    <form method="POST" action="{{ route('assets.transfer', $asset) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        @csrf
                        <div>
                            <label for="current_branch_id" class="block text-sm font-medium text-gray-700">Lokasi Tujuan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="current_branch_id" name="current_branch_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Pilih lokasi</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @disabled($branch->id === $asset->current_branch_id) @selected((string) old('current_branch_id') === (string) $branch->id)>{{ $branch->name }}@if ($branch->id === $asset->current_branch_id) (lokasi sekarang)@endif</option>
                                @endforeach
                            </select>
                            @error('current_branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="transfer_department_id" class="block text-sm font-medium text-gray-700">Divisi Tujuan</label>
                            <select id="transfer_department_id" name="department_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Tetap seperti sekarang</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="transfer_notes" class="block text-sm font-medium text-gray-700">Catatan</label>
                            <input id="transfer_notes" name="notes" value="{{ old('notes') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Alasan pemindahan">
                            @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2">
                            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Pindahkan</button>
                            <button type="button" data-panel-close="transfer" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        </div>
                    </form>
                </section>
            @endif
        @endcan

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Riwayat Perpindahan</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Ditulis sekali dan tidak pernah disunting.</p>
                </div>
                <span class="text-sm text-gray-500">{{ $asset->transactions->count() }} kejadian</span>
            </div>
            <ol class="divide-y divide-gray-100">
                @forelse ($asset->transactions as $event)
                    <li class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :tone="$event->type_tone">{{ $event->type_label }}</x-status-badge>
                                @if ($event->from_label || $event->to_label)
                                    <span class="text-sm text-gray-900">
                                        {{ $event->from_label ?: '—' }} <span class="text-gray-400">&rarr;</span> {{ $event->to_label ?: '—' }}
                                    </span>
                                @endif
                            </div>
                            @if ($event->notes)
                                <p class="mt-1 text-sm text-gray-600">{{ $event->notes }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-500">
                                oleh {{ $event->actor_name ?: ($event->actor?->name ?? 'Sistem') }}
                                @if ($event->condition) · kondisi {{ \App\Enums\AssetCondition::tryFrom($event->condition)?->label() }} @endif
                            </p>
                        </div>
                        <p class="shrink-0 text-xs text-gray-500">{{ $event->occurred_at?->translatedFormat('d M Y, H:i') }}</p>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-gray-400">Belum ada perpindahan yang tercatat.</li>
                @endforelse
            </ol>
        </section>

        @push('scripts')
        <script>
            // Panel serah-terima dibuka dari tombol di kartu pemegang. Ditulis di sini
            // dan bukan sebagai modal, supaya isian yang gagal validasi tetap terbaca
            // di halaman yang sama setelah kembali dari server.
            (function () {
                const open = (name) => {
                    document.querySelectorAll('[data-panel]').forEach((panel) => {
                        panel.hidden = panel.dataset.panel !== name;
                    });

                    document.querySelector(`[data-panel="${name}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                document.querySelectorAll('[data-panel-toggle]').forEach((button) => {
                    button.addEventListener('click', () => open(button.dataset.panelToggle));
                });

                document.querySelectorAll('[data-panel-close]').forEach((button) => {
                    button.addEventListener('click', () => {
                        document.querySelector(`[data-panel="${button.dataset.panelClose}"]`).hidden = true;
                    });
                });

                // Setelah validasi gagal, panel yang bersangkutan dibuka kembali sendiri
                // — kalau tidak, pesan kesalahannya tersembunyi dan formulir terlihat
                // seperti tidak terkirim.
                @if ($errors->any())
                    @php
                        $panel = $errors->has('employee_id') || $errors->has('condition_out') || $errors->has('expected_return_at') || $errors->has('purpose')
                            ? 'assign'
                            : ($errors->has('condition_in') || $errors->has('next_status') || $errors->has('return_notes') ? 'return' : 'transfer');
                    @endphp
                    open(@json($panel));
                @endif
            })();
        </script>
        @endpush

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
