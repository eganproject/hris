<x-layouts.app title="Ubah Aset - {{ config('app.name', 'HRIS') }}" heading="Ubah Aset">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-950">Ubah Aset</h1>
                <p class="mt-1 font-mono text-sm text-gray-500">{{ $asset->asset_code }}</p>
            </div>
            <a href="{{ route('assets.show', $asset) }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a>
        </div>
        <form method="POST" action="{{ route('assets.update', $asset) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('assets._form')
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan Perubahan</button>
        </form>
    </div>
</x-layouts.app>
