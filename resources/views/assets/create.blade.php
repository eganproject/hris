<x-layouts.app title="Tambah Aset - {{ config('app.name', 'HRIS') }}" heading="Tambah Aset">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-950">Tambah Aset</h1>
                <p class="mt-1 text-sm text-gray-500">Kode aset dibuat otomatis setelah data disimpan.</p>
            </div>
            <a href="{{ route('assets.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
        </div>
        <form method="POST" action="{{ route('assets.store') }}" class="space-y-6">
            @csrf
            @include('assets._form')
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan</button>
        </form>
    </div>
</x-layouts.app>
