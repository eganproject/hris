<x-layouts.app title="{{ $asset->asset_code }} - {{ config('app.name', 'HRIS') }}" heading="Detail Aset">
    @php
        $assignment = $asset->currentAssignment;
        $user = auth()->user();

        // Syarat tiap aksi dihitung SEKALI di sini, lalu dipakai bersama oleh tombolnya
        // dan panelnya. Sebelumnya syarat yang sama ditulis dua kali, dan sebuah tombol
        // yang membuka panel yang tidak pernah dirender adalah cacat yang tidak
        // kelihatan sampai seseorang mengkliknya.
        $canAssign = $user->can('asset-assignments.assign') && $asset->status?->isAssignable();
        $canReturn = $user->can('asset-assignments.return') && $assignment !== null;
        $canTransfer = $user->can('asset-assignments.transfer') && $assignment === null && ! $asset->status?->isClosed();
        $hasActions = $canAssign || $canReturn || $canTransfer;

        // Panel yang harus terbuka kembali setelah validasi gagal — kalau tidak, pesan
        // kesalahannya tersembunyi dan formulirnya terlihat seperti tidak terkirim.
        $openPanel = match (true) {
            $errors->hasAny(['employee_id', 'condition_out', 'expected_return_at', 'purpose']) => 'assign',
            $errors->hasAny(['condition_in', 'next_status', 'return_notes']) => 'return',
            $errors->hasAny(['current_branch_id', 'department_id', 'notes']) => 'transfer',
            default => null,
        };

        // Alasan yang sama untuk tab: galat unggahan berkas hidup di tab Berkas.
        $initialTab = $errors->hasAny(['file', 'type', 'title']) ? 'berkas' : null;

        // Kelas isian ditulis sekali. Halaman ini punya delapan kolom isian di tiga
        // panel; mengulang rentetan kelas yang sama di tiap kolom membuat perbedaan
        // satu huruf pun tidak akan pernah tertangkap mata.
        $field = 'mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';
        $label = 'block text-sm font-medium text-gray-700';
        $hint = 'mt-1 text-xs text-gray-500';
    @endphp

    <div class="mx-auto max-w-6xl space-y-5" data-tabs data-tabs-storage-key="asset-detail-tab" @if ($initialTab) data-tabs-initial="{{ $initialTab }}" @endif>

        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('assets.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-900">
                    <span aria-hidden="true">&larr;</span> Daftar Aset
                </a>
                <h1 class="mt-2 truncate text-2xl font-semibold text-gray-950">{{ $asset->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="rounded bg-gray-100 px-2 py-1 font-mono text-xs font-medium text-gray-700">{{ $asset->asset_code }}</span>
                    <x-status-badge :tone="$asset->status_tone">{{ $asset->status_label }}</x-status-badge>
                    <x-status-badge :tone="$asset->condition_tone">{{ $asset->condition_label }}</x-status-badge>
                    <span class="text-sm text-gray-500">{{ $asset->category?->name }}</span>
                </div>
            </div>

            @can('assets.update')
                @unless ($asset->status?->isClosed())
                    <a href="{{ route('assets.edit', $asset) }}" class="inline-flex flex-none items-center gap-2 rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                        <x-icon name="pencil"/> Edit Aset
                    </a>
                @endunless
            @endcan
        </section>

        {{-- Kartu utama: jawaban yang dicari orang saat membuka halaman ini — barangnya
             ada pada siapa, dan di mana. Sengaja paling atas dan paling lapang. --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="p-5 sm:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pemegang Saat Ini</p>

                    @if ($assignment)
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $assignment->employee?->full_name ?? '—' }}</p>
                        <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $assignment->employee?->employee_number }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($assignment->isAcknowledged())
                                <x-status-badge tone="success">Dikonfirmasi {{ $assignment->acknowledged_at->translatedFormat('d M Y') }}</x-status-badge>
                            @else
                                <x-status-badge tone="warning">Menunggu konfirmasi karyawan</x-status-badge>
                            @endif

                            @if ($assignment->isOverdue())
                                <x-status-badge tone="danger">Telat {{ $assignment->expected_return_at->diffInDays(today()) }} hari</x-status-badge>
                            @endif
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-gray-500">Diserahkan</dt>
                                <dd class="mt-0.5 text-gray-900">{{ $assignment->assigned_at?->translatedFormat('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Target kembali</dt>
                                <dd class="mt-0.5 text-gray-900">{{ $assignment->expected_return_at?->translatedFormat('d M Y') ?? 'Tanpa batas' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Kondisi diserahkan</dt>
                                <dd class="mt-0.5 text-gray-900">{{ $assignment->condition_out_label }}</dd>
                            </div>
                            @if ($assignment->purpose)
                                <div class="col-span-2 sm:col-span-3">
                                    <dt class="text-xs text-gray-500">Keperluan</dt>
                                    <dd class="mt-0.5 text-gray-900">{{ $assignment->purpose }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="mt-2 text-lg font-semibold text-gray-400">Tidak dipegang siapa pun</p>
                        <p class="mt-1 text-sm text-gray-500">
                            @if ($asset->status?->isAssignable())
                                Aset siap diserahkan kepada karyawan.
                            @else
                                Aset berstatus &ldquo;{{ $asset->status_label }}&rdquo;, jadi belum bisa diserahkan.
                            @endif
                        </p>
                    @endif
                </div>

                <div class="space-y-4 bg-gray-50/60 p-5">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Lokasi Sekarang</p>
                        <p class="mt-1 text-sm font-medium text-gray-950">{{ $asset->currentBranch?->name ?? '—' }}</p>
                        @if ($asset->owning_branch_id !== $asset->current_branch_id)
                            <p class="mt-0.5 text-xs text-amber-600">Dititipkan — pemiliknya {{ $asset->owningBranch?->name }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Divisi Pemilik</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $asset->departments->pluck('name')->implode(', ') ?: ($asset->department?->name ?? '—') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Garansi</p>
                        @if ($asset->warranty_expires_at)
                            <p @class(['mt-1 text-sm', 'font-medium text-red-600' => $asset->warranty_is_expired, 'font-medium text-amber-600' => $asset->warranty_is_expiring, 'text-gray-900' => ! $asset->warranty_is_expired && ! $asset->warranty_is_expiring])>{{ $asset->warranty_expires_at->translatedFormat('d M Y') }}</p>
                            @if ($asset->warranty_is_expired)
                                <p class="mt-0.5 text-xs text-red-600">Sudah berakhir</p>
                            @elseif ($asset->warranty_is_expiring)
                                <p class="mt-0.5 text-xs text-amber-600">Berakhir dalam {{ $asset->warranty_expires_at->diffInDays(today()) }} hari</p>
                            @endif
                        @else
                            <p class="mt-1 text-sm text-gray-400">Tidak dicatat</p>
                        @endif
                    </div>
                </div>
            </div>

            @if ($hasActions)
                <div class="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-4">
                    @if ($canAssign)
                        <button type="button" data-panel-toggle="assign" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Serahkan ke Karyawan</button>
                    @endif
                    @if ($canReturn)
                        <button type="button" data-panel-toggle="return" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Terima Kembali</button>
                    @endif
                    @if ($canTransfer)
                        <button type="button" data-panel-toggle="transfer" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Pindahkan Lokasi</button>
                    @endif
                </div>
            @endif
        </section>

        {{-- Panel aksi. Ditulis sebagai bagian halaman, bukan modal, supaya isian yang
             gagal validasi tetap terbaca di tempat yang sama setelah kembali dari
             server — modal akan tertutup dan pesannya ikut hilang. --}}
        @if ($canAssign)
            <section data-panel="assign" hidden class="rounded-xl border border-primary/30 bg-white p-5 shadow-sm ring-1 ring-primary/10 sm:p-6">
                <h2 class="text-base font-semibold text-gray-950">Serahkan ke Karyawan</h2>
                <p class="mt-1 text-sm text-gray-500">Karyawan yang menerima akan diminta mengonfirmasi penerimaannya lewat menu Aset Saya.</p>

                <form method="POST" action="{{ route('assets.assign', $asset) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                    @csrf
                    <div class="md:col-span-2">
                        <label for="employee_id" class="{{ $label }}">Karyawan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <select id="employee_id" name="employee_id" required class="{{ $field }}">
                            <option value="">Pilih karyawan</option>
                            @foreach ($employees as $person)
                                <option value="{{ $person->id }}" @selected((string) old('employee_id') === (string) $person->id)>{{ $person->full_name }} — {{ $person->employee_number }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="condition_out" class="{{ $label }}">Kondisi Saat Diserahkan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <select id="condition_out" name="condition_out" required class="{{ $field }}">
                            @foreach ($serviceableConditions as $value => $text)
                                <option value="{{ $value }}" @selected(old('condition_out', $asset->condition?->value) === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $hint }}">Aset rusak atau tidak layak tidak boleh diserahkan.</p>
                        @error('condition_out')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="expected_return_at" class="{{ $label }}">Target Pengembalian</label>
                        <input id="expected_return_at" name="expected_return_at" type="date" min="{{ today()->format('Y-m-d') }}" value="{{ old('expected_return_at') }}" class="{{ $field }}">
                        <p class="{{ $hint }}">Kosongkan bila dipegang untuk seterusnya.</p>
                        @error('expected_return_at')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="purpose" class="{{ $label }}">Keperluan</label>
                        <input id="purpose" name="purpose" value="{{ old('purpose') }}" class="{{ $field }}" placeholder="Kerja harian, dinas ke Surabaya, dsb.">
                        @error('purpose')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row md:col-span-2">
                        <button type="button" data-panel-close="assign" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Serahkan</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canReturn)
            <section data-panel="return" hidden class="rounded-xl border border-primary/30 bg-white p-5 shadow-sm ring-1 ring-primary/10 sm:p-6">
                <h2 class="text-base font-semibold text-gray-950">Terima Kembali</h2>
                <p class="mt-1 text-sm text-gray-500">Periksa barangnya dulu — kondisi yang dicatat di sini menentukan status aset berikutnya.</p>

                <form method="POST" action="{{ route('assets.return', $asset) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                    @csrf
                    <div>
                        <label for="condition_in" class="{{ $label }}">Kondisi Saat Kembali <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <select id="condition_in" name="condition_in" required class="{{ $field }}">
                            @foreach ($conditions as $value => $text)
                                <option value="{{ $value }}" @selected(old('condition_in', $asset->condition?->value) === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                        @error('condition_in')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="next_status" class="{{ $label }}">Status Setelah Kembali</label>
                        <select id="next_status" name="next_status" class="{{ $field }}">
                            <option value="">Ikuti kondisi barangnya</option>
                            @foreach ($returnOutcomes as $value => $text)
                                <option value="{{ $value }}" @selected(old('next_status') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $hint }}">Rusak jadi Perawatan, tidak layak jadi Tidak Dipakai. Pilih sendiri bila perlu lain.</p>
                        @error('next_status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="return_notes" class="{{ $label }}">Catatan Pemeriksaan</label>
                        <textarea id="return_notes" name="return_notes" rows="2" class="{{ $field }}" placeholder="Lecet di sudut kiri, charger lengkap, dsb.">{{ old('return_notes') }}</textarea>
                        @error('return_notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row md:col-span-2">
                        <button type="button" data-panel-close="return" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Catat Pengembalian</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canTransfer)
            <section data-panel="transfer" hidden class="rounded-xl border border-primary/30 bg-white p-5 shadow-sm ring-1 ring-primary/10 sm:p-6">
                <h2 class="text-base font-semibold text-gray-950">Pindahkan Lokasi</h2>
                <p class="mt-1 text-sm text-gray-500">Yang berpindah adalah tempat barangnya berada. Cabang pemiliknya, dan kode asetnya, tidak ikut berubah.</p>

                <form method="POST" action="{{ route('assets.transfer', $asset) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                    @csrf
                    <div>
                        <label for="current_branch_id" class="{{ $label }}">Lokasi Tujuan <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                        <select id="current_branch_id" name="current_branch_id" required class="{{ $field }}">
                            <option value="">Pilih lokasi</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @disabled($branch->id === $asset->current_branch_id) @selected((string) old('current_branch_id') === (string) $branch->id)>{{ $branch->name }}@if ($branch->id === $asset->current_branch_id) (lokasi sekarang)@endif</option>
                            @endforeach
                        </select>
                        @error('current_branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="transfer_department_id" class="{{ $label }}">Divisi Tujuan</label>
                        <select id="transfer_department_id" name="department_id" class="{{ $field }}">
                            <option value="">Tetap seperti sekarang</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="transfer_notes" class="{{ $label }}">Catatan</label>
                        <input id="transfer_notes" name="notes" value="{{ old('notes') }}" class="{{ $field }}" placeholder="Alasan pemindahan">
                        @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row md:col-span-2">
                        <button type="button" data-panel-close="transfer" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Pindahkan</button>
                    </div>
                </form>
            </section>
        @endif

        {{-- Tiga tab, bukan tiga kartu bertumpuk. Halaman ini punya empat urusan yang
             berbeda, dan menampilkan semuanya sekaligus memaksa orang menggulir jauh
             melewati yang tidak sedang ia cari. --}}
        <nav class="grid grid-cols-3 gap-1 rounded-lg border border-gray-200 bg-white p-1 shadow-sm" role="tablist" aria-label="Bagian detail aset">
            <button type="button" role="tab" data-tab-button="detail" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Detail</button>
            <button type="button" role="tab" data-tab-button="riwayat" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Riwayat ({{ $asset->transactions->count() }})</button>
            <button type="button" role="tab" data-tab-button="berkas" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Berkas ({{ $asset->documents->count() }})</button>
        </nav>

        {{-- Detail. Sengaja tidak mengulang lokasi, divisi, dan garansi: ketiganya sudah
             ada di kartu atas, dan menuliskannya dua kali membuat pembacanya ragu mana
             yang berlaku. --}}
        <section data-tab-panel="detail" role="tabpanel" class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Detail Aset</h2>
                <p class="mt-1 text-sm text-gray-500">Identitas barang dan catatan perolehannya.</p>
            </div>

            <dl class="grid grid-cols-1 gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Jenis / Merek &amp; Tipe</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ collect([$asset->brand, $asset->model])->filter()->implode(' ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Nomor Seri</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-900">{{ $asset->serial_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Kategori</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $asset->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Lokasi Pemilik</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $asset->owningBranch?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tanggal Perolehan</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $asset->acquired_at?->translatedFormat('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Nilai Perolehan</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $asset->acquisition_cost !== null ? 'Rp '.number_format((float) $asset->acquisition_cost, 0, ',', '.') : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Umur Ekonomis</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $asset->category?->useful_life_label ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Didaftarkan Oleh</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $asset->creator?->name ?? '—' }} <span class="text-gray-400">· {{ $asset->created_at?->translatedFormat('d M Y') }}</span></dd>
                </div>

                @if ($asset->specification)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Spesifikasi</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $asset->specification }}</dd>
                    </div>
                @endif

                @if ($asset->notes)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Catatan</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $asset->notes }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- Riwayat. Ditulis sekali dan tidak pernah bisa disunting, jadi ditampilkan
             sebagai garis waktu — bukan tabel yang mengundang orang mencari tombol edit. --}}
        <section data-tab-panel="riwayat" role="tabpanel" hidden class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Riwayat Perpindahan</h2>
                <p class="mt-1 text-sm text-gray-500">Setiap kejadian dicatat sekali dan tidak pernah diubah.</p>
            </div>

            @forelse ($asset->transactions as $event)
                <div @class(['flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between', 'border-t border-gray-100' => ! $loop->first])>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :tone="$event->type_tone">{{ $event->type_label }}</x-status-badge>
                            @if ($event->from_label || $event->to_label)
                                <span class="text-sm text-gray-900">
                                    {{ $event->from_label ?: '—' }}
                                    <span class="mx-0.5 text-gray-400" aria-hidden="true">&rarr;</span>
                                    {{ $event->to_label ?: '—' }}
                                </span>
                            @endif
                        </div>

                        @if ($event->notes)
                            <p class="mt-1.5 text-sm text-gray-600">{{ $event->notes }}</p>
                        @endif

                        <p class="mt-1.5 text-xs text-gray-500">
                            oleh {{ $event->actor_name ?: ($event->actor?->name ?? 'Sistem') }}
                            @if ($event->condition)
                                <span class="mx-1 text-gray-300" aria-hidden="true">&middot;</span>
                                kondisi {{ \App\Enums\AssetCondition::tryFrom($event->condition)?->label() }}
                            @endif
                        </p>
                    </div>
                    <p class="flex-none text-xs text-gray-500 sm:text-right">{{ $event->occurred_at?->translatedFormat('d M Y') }} <span class="text-gray-400">{{ $event->occurred_at?->format('H:i') }}</span></p>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-gray-400">Belum ada perpindahan yang tercatat untuk aset ini.</p>
            @endforelse
        </section>

        {{-- Berkas. Formulir unggahnya dilipat: yang dicari orang saat membuka tab ini
             hampir selalu berkas yang sudah ada, bukan menambah yang baru. --}}
        <section data-tab-panel="berkas" role="tabpanel" hidden class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Berkas Aset</h2>
                <p class="mt-1 text-sm text-gray-500">Faktur, kartu garansi, foto kondisi, dan berita acara. Disimpan privat — hanya bisa dibuka lewat halaman ini.</p>
            </div>

            @can('assets.update')
                <details class="group border-b border-gray-100 bg-gray-50/60" @if ($initialTab === 'berkas') open @endif>
                    <summary class="cursor-pointer select-none px-5 py-3 text-sm font-semibold text-primary transition hover:text-primary-hover">
                        <span class="group-open:hidden">+ Unggah berkas baru</span>
                        <span class="hidden group-open:inline">&minus; Tutup formulir unggah</span>
                    </summary>

                    <form method="POST" action="{{ route('assets.documents.store', $asset) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 px-5 pb-5 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="type" class="{{ $label }}">Jenis Berkas <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                            <select id="type" name="type" required class="{{ $field }}">
                                @foreach (\App\Models\AssetDocument::TYPE_LABELS as $value => $text)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                            @error('type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="title" class="{{ $label }}">Keterangan</label>
                            <input id="title" name="title" value="{{ old('title') }}" class="{{ $field }}" placeholder="Faktur pembelian Juni 2026">
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
                            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Unggah Berkas</button>
                        </div>
                    </form>
                </details>
            @endcan

            @forelse ($asset->documents as $document)
                <div @class(['flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between', 'border-t border-gray-100' => ! $loop->first])>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-950">{{ $document->title ?: $document->original_name }}</p>
                        <p class="mt-0.5 truncate text-xs text-gray-500">
                            {{ $document->type_label }}
                            <span class="mx-1 text-gray-300" aria-hidden="true">&middot;</span>{{ $document->sizeLabel() }}
                            <span class="mx-1 text-gray-300" aria-hidden="true">&middot;</span>{{ $document->uploader?->name ?? '—' }}, {{ $document->created_at?->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <div class="flex flex-none items-center gap-2">
                        <a href="{{ route('assets.documents.show', [$asset, $document]) }}" target="_blank" rel="noopener" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">Buka</a>
                        <a href="{{ route('assets.documents.show', [$asset, $document]) }}?download=1" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">Unduh</a>
                        @can('assets.update')
                            <form method="POST" action="{{ route('assets.documents.destroy', [$asset, $document]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">Hapus</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-gray-400">Belum ada berkas untuk aset ini.</p>
            @endforelse
        </section>
    </div>

    @if ($hasActions)
        <span data-panel-initial="{{ $openPanel }}" hidden></span>

        @push('scripts')
        <script>
            // Panel serah-terima dibuka dari tombol di kartu pemegang. Hanya satu yang
            // boleh terbuka: dua formulir sekaligus membuat halaman melompat dan
            // menyisakan keraguan yang mana yang akan terkirim.
            (function () {
                const panels = [...document.querySelectorAll('[data-panel]')];

                if (panels.length === 0) return;

                const open = (name) => {
                    panels.forEach((panel) => { panel.hidden = panel.dataset.panel !== name; });
                    panels.find((panel) => panel.dataset.panel === name)
                        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                document.querySelectorAll('[data-panel-toggle]').forEach((button) => {
                    button.addEventListener('click', () => open(button.dataset.panelToggle));
                });

                document.querySelectorAll('[data-panel-close]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const panel = panels.find((item) => item.dataset.panel === button.dataset.panelClose);

                        if (panel) panel.hidden = true;
                    });
                });

                // Setelah validasi gagal, panel yang bersangkutan dibuka kembali sendiri
                // — kalau tidak, pesan kesalahannya tersembunyi dan formulir terlihat
                // seperti tidak terkirim. Mana panelnya diputuskan di sisi server.
                const initial = document.querySelector('[data-panel-initial]')?.dataset.panelInitial;

                if (initial) open(initial);
            })();
        </script>
        @endpush
    @endif
</x-layouts.app>
