{{-- Ubin pintasan di kartu "Aksi Cepat". --}}
@props(['href', 'icon', 'label'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group flex items-center gap-2.5 rounded-md border border-gray-200 px-3 py-2.5 transition hover:border-gray-300 hover:bg-gray-50']) }}>
    <span class="flex size-7 flex-none items-center justify-center rounded-md bg-gray-100 text-gray-500 transition group-hover:bg-primary group-hover:text-white">
        <x-icon :name="$icon" class="size-4"/>
    </span>
    <span class="min-w-0 truncate text-sm font-medium text-gray-700">{{ $label }}</span>
</a>
