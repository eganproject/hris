@php
    $index = $index ?? 0;
    $row = $row ?? ['device_id' => null, 'machine_user_id' => ''];
@endphp
<div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_140px_auto] sm:items-start" data-pin-row>
    <div>
        <select name="machine_pins[{{ $index }}][device_id]" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            <option value="">Semua mesin (global)</option>
            @foreach ($devices as $device)
                <option value="{{ $device->id }}" @selected((string) ($row['device_id'] ?? '') === (string) $device->id)>{{ $device->name }} — {{ $device->branch?->name ?? 'Tanpa lokasi' }}</option>
            @endforeach
        </select>
        @error("machine_pins.$index.device_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <input name="machine_pins[{{ $index }}][machine_user_id]" value="{{ $row['machine_user_id'] ?? '' }}" inputmode="numeric" placeholder="PIN" class="block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        @error("machine_pins.$index.machine_user_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <button type="button" data-pin-remove class="inline-flex h-[42px] items-center justify-center rounded-md border border-gray-200 px-3 text-sm font-medium text-gray-600 hover:bg-gray-50" aria-label="Hapus baris PIN">Hapus</button>
</div>
