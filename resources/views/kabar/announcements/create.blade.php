<x-layouts.app title="Tulis Pengumuman - {{ config('app.name', 'HRIS') }}" heading="Tulis Pengumuman">
    <form method="POST" action="{{ route('kabar.announcements.store') }}" enctype="multipart/form-data" class="mx-auto max-w-3xl space-y-6">
        @csrf
        <section class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-950">Tulis Pengumuman</h1>
            <a href="{{ route('kabar.announcements.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
        </section>

        @include('kabar.announcements._form')

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Terbitkan</button>
        </div>
    </form>
</x-layouts.app>
