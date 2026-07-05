<x-layouts.app title="Edit Shift - {{ config('app.name', 'HRIS') }}" heading="Edit Shift">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4"><h1 class="text-2xl font-semibold text-gray-950">Edit Shift</h1><a href="{{ route('attendance.shifts.index') }}" class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali</a></div>
        <form method="POST" action="{{ route('attendance.shifts.update', $shift) }}" class="space-y-6">@csrf @method('PUT') @include('attendance.shifts._form')<button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-hover">Simpan Perubahan</button></form>
    </div>
</x-layouts.app>
