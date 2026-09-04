<x-layouts.app title="Ubah Tempat Penyimpanan - {{ config('app.name', 'HRIS') }}" heading="Ubah Tempat Penyimpanan">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-950">Ubah Tempat Penyimpanan</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $storageLocation->full_path }}</p>
            </div>
            <a href="{{ route('assets.storage-locations.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
        </div>
        <form method="POST" action="{{ route('assets.storage-locations.update', $storageLocation) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('assets.storage-locations._form')
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan Perubahan</button>
        </form>
    </div>
</x-layouts.app>
