<x-layouts.app title="Edit Jabatan - {{ config('app.name', 'HRIS') }}" heading="Edit Jabatan">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4"><h1 class="text-2xl font-semibold text-gray-950">Edit Jabatan</h1><a href="{{ route('organization.job-positions.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a></div>
        <form method="POST" action="{{ route('organization.job-positions.update', $jobPosition) }}" class="space-y-6">@csrf @method('PUT') @include('organization.job-positions._form')<button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan Perubahan</button></form>
    </div>
</x-layouts.app>
