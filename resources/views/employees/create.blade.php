<x-layouts.app title="Tambah Karyawan - {{ config('app.name', 'HRIS') }}" heading="Tambah Karyawan">
    <form method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data" class="mx-auto max-w-7xl space-y-6">
        @csrf

        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Employee module</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Tambah Karyawan</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('employees.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
            </div>
        </section>

        @include('employees._form', ['employee' => new \App\Models\Employee()])
    </form>
</x-layouts.app>
