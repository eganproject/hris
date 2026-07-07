@php
    use App\Enums\SchedulePatternType;

    // Weekly slots use Carbon's dayOfWeek index (0=Minggu..6=Sabtu); present Mon-first.
    $weekdays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'];
    $dayShift = $pattern->days->mapWithKeys(fn ($day) => [$day->day_index => $day->shift_id])->all();
    $currentType = old('type', $pattern->type?->value ?? SchedulePatternType::FixedWeekly->value);
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Kode <span class="field-requirement is-required">*</span></label>
            <input id="code" name="code" value="{{ old('code', $pattern->code) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Pola <span class="field-requirement is-required">*</span></label>
            <input id="name" name="name" value="{{ old('name', $pattern->name) }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Tipe Pola <span class="field-requirement is-required">*</span></label>
            <select id="type" name="type" data-pattern-type class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                @foreach (SchedulePatternType::options() as $value => $label)
                    <option value="{{ $value }}" @selected($currentType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @foreach (SchedulePatternType::cases() as $case)
                <p class="mt-2 text-xs text-gray-500" data-type-desc="{{ $case->value }}" @style(['display:none' => $currentType !== $case->value])>{{ $case->description() }}</p>
            @endforeach
        </div>
        <label class="flex items-end gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pattern->is_active ?? true)) class="mb-3 size-4 rounded border-gray-300 text-primary focus:ring-primary">
            <span class="pb-2.5">Aktif</span>
        </label>
    </div>
</section>

{{-- Weekly pattern: one shift per weekday --}}
<section data-block="fixed_weekly" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" @style(['display:none' => $currentType !== 'fixed_weekly'])>
    <h3 class="text-sm font-semibold text-gray-950">Jadwal Mingguan</h3>
    <p class="mt-1 text-xs text-gray-500">Pilih shift untuk tiap hari. Kosongkan (Libur) untuk hari libur.</p>
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($weekdays as $index => $label)
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                <select name="days[{{ $index }}]" @disabled($currentType !== 'fixed_weekly') data-weekly-select class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Libur</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old("days.$index", $dayShift[$index] ?? '') === (string) $shift->id)>{{ $shift->code }} — {{ $shift->name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>
</section>

{{-- Rotating pattern: a cycle of N days that repeats from an anchor date --}}
<section data-block="rotating" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" @style(['display:none' => $currentType !== 'rotating'])>
    <h3 class="text-sm font-semibold text-gray-950">Rotasi</h3>
    <div class="mt-3 grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="cycle_length" class="block text-sm font-medium text-gray-700">Panjang Siklus (hari) <span class="field-requirement is-required">*</span></label>
            <input id="cycle_length" name="cycle_length" type="number" min="1" max="60" value="{{ old('cycle_length', $pattern->cycle_length ?: 4) }}" data-cycle-length class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            @error('cycle_length')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="anchor_date" class="block text-sm font-medium text-gray-700">Tanggal Jangkar (Hari ke-1) <span class="field-requirement is-required">*</span></label>
            <input id="anchor_date" name="anchor_date" type="date" value="{{ old('anchor_date', optional($pattern->anchor_date)->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            <p class="mt-2 text-xs text-gray-500">Siklus mulai dihitung dari tanggal ini.</p>
            @error('anchor_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" data-rotating-slots></div>
</section>

@push('scripts')
<script>
    (function () {
        const typeSelect = document.querySelector('[data-pattern-type]');
        if (!typeSelect) return;

        const shifts = @json($shifts->map(fn ($s) => ['id' => $s->id, 'label' => $s->code.' — '.$s->name])->values());
        const dayShift = @json((object) $dayShift);
        const oldRotating = @json((object) (old('type') === 'rotating' ? old('days', []) : []));
        const cycleInput = document.querySelector('[data-cycle-length]');
        const slotsEl = document.querySelector('[data-rotating-slots]');

        function optionsHtml(selected) {
            let html = '<option value="">Libur</option>';
            shifts.forEach(function (s) {
                const sel = String(selected) === String(s.id) ? ' selected' : '';
                html += '<option value="' + s.id + '"' + sel + '>' + s.label + '</option>';
            });
            return html;
        }

        function buildRotating() {
            const n = Math.max(1, Math.min(60, parseInt(cycleInput.value) || 1));
            const rotating = typeSelect.value === 'rotating';
            slotsEl.innerHTML = '';
            for (let i = 0; i < n; i++) {
                const selected = (oldRotating[i] !== undefined ? oldRotating[i] : (dayShift[i] ?? ''));
                const wrap = document.createElement('div');
                wrap.innerHTML = '<label class="block text-sm font-medium text-gray-700">Hari ke-' + (i + 1) + '</label>' +
                    '<select name="days[' + i + ']" ' + (rotating ? '' : 'disabled') + ' class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">' +
                    optionsHtml(selected) + '</select>';
                slotsEl.appendChild(wrap);
            }
        }

        function syncType() {
            const type = typeSelect.value;
            document.querySelectorAll('[data-block]').forEach(function (block) {
                const on = block.getAttribute('data-block') === type;
                block.style.display = on ? '' : 'none';
                // Disable inputs in hidden blocks so their empty values don't overwrite data.
                block.querySelectorAll('select, input').forEach(function (el) { el.disabled = !on; });
            });
            document.querySelectorAll('[data-type-desc]').forEach(function (d) {
                d.style.display = d.getAttribute('data-type-desc') === type ? '' : 'none';
            });
            if (type === 'rotating') buildRotating();
        }

        typeSelect.addEventListener('change', syncType);
        cycleInput.addEventListener('input', buildRotating);
        buildRotating();
        syncType();
    })();
</script>
@endpush
