<x-layouts.app title="Tambah Kategori Aset - {{ config('app.name', 'HRIS') }}" heading="Tambah Kategori Aset">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-950">Tambah Kategori Aset</h1>
            <a href="{{ route('assets.categories.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
        </div>
        <form method="POST" action="{{ route('assets.categories.store') }}" class="space-y-6">
            @csrf
            @include('assets.categories._form')
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan</button>
        </form>
    </div>
</x-layouts.app>
