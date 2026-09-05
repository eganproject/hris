<x-layouts.app title="Ajukan Cuti/Izin - {{ config('app.name', 'HRIS') }}" heading="Ajukan Cuti/Izin">
    <form method="POST" enctype="multipart/form-data" action="{{ route('my-leave.store') }}" class="mx-auto max-w-3xl space-y-6">
        @csrf
        <section class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-950">Ajukan Cuti / Izin</h1>
            <a href="{{ route('my-leave.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
        </section>

        @if ($balances)
            <section class="flex flex-wrap gap-3">
                @foreach ($balances as $balance)
                    <span class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-600 shadow-xs">
                        {{ $balance['type']->name }}: <span class="font-semibold text-gray-900">{{ $balance['remaining'] }}</span> / {{ $balance['quota'] }} hari
                    </span>
                @endforeach
            </section>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="leave_type_id" class="block text-sm font-medium text-gray-700">Jenis <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <select id="leave_type_id" name="leave_type_id" required data-attachment-requirement="#attachment-field" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">— Pilih jenis —</option>
                        @foreach ($leaveTypes as $type)
                            {{-- Jenis sakit mewajibkan surat keterangan. Penandanya dari
                                 server, sehingga aturannya sama dengan yang divalidasi. --}}
                            <option value="{{ $type->id }}"
                                @if ($type->attendance_status === \App\Enums\AttendanceStatus::Sick)
                                    data-requires-attachment="Surat keterangan sakit wajib dilampirkan. Gambar (JPG, PNG, WEBP) atau PDF, maksimal {{ \App\Models\LeaveRequest::ATTACHMENT_MAX_MB }} MB."
                                @endif
                                @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @php($earliestStart = now()->subDays(\App\Models\LeaveRequest::SELF_BACKDATE_DAYS)->format('Y-m-d'))
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    {{-- Batas mundurnya sama dengan yang divalidasi di server, jadi pemilih
                         tanggal tidak menawarkan tanggal yang pasti ditolak. --}}
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->format('Y-m-d')) }}" min="{{ $earliestStart }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <p class="mt-2 text-xs text-gray-500">Boleh mundur sampai {{ \App\Models\LeaveRequest::SELF_BACKDATE_DAYS }} hari ke belakang (paling awal {{ now()->subDays(\App\Models\LeaveRequest::SELF_BACKDATE_DAYS)->translatedFormat('d M Y') }}) untuk sakit atau izin yang baru sempat diajukan.</p>
                    @error('start_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Selesai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', now()->format('Y-m-d')) }}" min="{{ $earliestStart }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @error('end_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="reason" class="block text-sm font-medium text-gray-700">Alasan</label>
                    <textarea id="reason" name="reason" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <x-attachment-field name="attachment" label="Lampiran" :max-mb="\App\Models\LeaveRequest::ATTACHMENT_MAX_MB" />
            </div>
            <p class="mt-4 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-500">Pengajuan akan diteruskan ke atasan Anda, dan keputusan atasan bersifat final. Bila Anda belum punya atasan, HR yang memutuskan.</p>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Ajukan</button>
        </div>
    </form>
</x-layouts.app>
