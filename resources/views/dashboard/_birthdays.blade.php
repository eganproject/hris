{{-- Papan ulang tahun bulan berjalan. Tanggalnya sengaja tanpa tahun: yang
     dirayakan tanggalnya, bukan usianya. --}}
@php
    $celebrantsToday = $birthdays->filter(fn ($person) => $person->birth_date->isBirthday());
    $canOpenEmployee = (bool) auth()->user()?->can('employees.view');
@endphp

<x-dashboard.card
    title="Ulang Tahun Bulan Ini"
    :subtitle="now()->translatedFormat('F Y').($birthdays->isNotEmpty() ? ' · '.$birthdays->count().' karyawan' : '')"
    icon="cake"
    tone="amber"
    flush>
    @if ($celebrantsToday->isNotEmpty())
        <x-slot:action>
            <span class="rounded-md bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800">
                🎉 {{ $celebrantsToday->count() }} hari ini
            </span>
        </x-slot:action>
    @endif

    <ul class="divide-y divide-gray-50">
        @forelse ($birthdays as $person)
            @php
                $isToday = $person->birth_date->isBirthday();
                // Tanggal & bulan saja — tahun lahirnya tidak pernah dirender.
                $dayMonth = $person->birth_date->translatedFormat('d M');
            @endphp

            <li @class([
                'relative flex items-center gap-3 px-5 py-3',
                'bg-amber-50/60' => $isToday,
                'transition hover:bg-gray-50' => $canOpenEmployee && ! $isToday,
                'transition hover:bg-amber-50' => $canOpenEmployee && $isToday,
            ])>
                @if ($person->photo_url)
                    <img src="{{ $person->photo_url }}" alt="Foto {{ $person->full_name }}"
                        class="size-9 flex-none rounded-full border border-gray-200 object-cover">
                @else
                    <span class="flex size-9 flex-none items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500" aria-hidden="true">
                        {{ str($person->full_name)->substr(0, 1)->upper() }}
                    </span>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900">{{ $person->full_name }}</p>
                    <p class="truncate text-xs text-gray-500">{{ $person->jobPosition?->name ?? 'Jabatan belum diatur' }}</p>
                </div>

                <div class="flex-none text-right">
                    <p @class(['text-sm font-semibold', 'text-amber-700' => $isToday, 'text-gray-700' => ! $isToday])>{{ $dayMonth }}</p>
                    @if ($isToday)
                        <p class="mt-0.5 text-[11px] font-semibold text-amber-700">Hari ini 🎉</p>
                    @endif
                </div>

                {{-- Tautan melebar menutupi seluruh baris, jadi barisnya tetap satu
                     elemen daftar yang rapi tanpa membungkus semuanya dalam <a>. --}}
                @if ($canOpenEmployee)
                    <a href="{{ route('employees.show', $person) }}" class="absolute inset-0">
                        <span class="sr-only">Lihat detail {{ $person->full_name }}</span>
                    </a>
                @endif
            </li>
        @empty
            <li class="px-5 py-10 text-center">
                <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <x-icon name="cake" class="size-5"/>
                </span>
                <p class="mt-3 text-sm font-medium text-gray-700">Belum ada yang berulang tahun</p>
                <p class="mt-1 text-xs text-gray-500">Tidak ada karyawan dengan tanggal lahir di bulan {{ now()->translatedFormat('F') }}.</p>
            </li>
        @endforelse
    </ul>
</x-dashboard.card>
