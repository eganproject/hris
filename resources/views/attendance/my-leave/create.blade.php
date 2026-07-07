<x-layouts.app title="Ajukan Cuti/Izin - {{ config('app.name', 'HRIS') }}" heading="Ajukan Cuti/Izin">
    <form method="POST" action="{{ route('my-leave.store') }}" class="mx-auto max-w-3xl space-y-6">
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
                    <select id="leave_type_id" name="leave_type_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">— Pilih jenis —</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @error('start_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Selesai <span class="field-requirement is-required" aria-label="Wajib diisi">*</span></label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', now()->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @error('end_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="reason" class="block text-sm font-medium text-gray-700">Alasan</label>
                    <textarea id="reason" name="reason" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="mt-4 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-500">Pengajuan akan diteruskan ke atasan Anda, lalu ke HR untuk persetujuan akhir.</p>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Ajukan</button>
        </div>
    </form>
</x-layouts.app>
