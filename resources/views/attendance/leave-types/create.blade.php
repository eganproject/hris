<x-layouts.app title="Tambah Jenis Cuti - {{ config('app.name', 'HRIS') }}" heading="Tambah Jenis Cuti">
    <form method="POST" action="{{ route('attendance.leave-types.store') }}" class="mx-auto max-w-3xl space-y-6">
        @csrf
        <section class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-950">Tambah Jenis Cuti</h1>
            <a href="{{ route('attendance.leave-types.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
        </section>

        @include('attendance.leave-types._form')

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan</button>
        </div>
    </form>
</x-layouts.app>
