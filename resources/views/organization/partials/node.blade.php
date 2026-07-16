@php
    /** @var \App\Models\Employee $employee */
    $employee = $node['employee'];
    $children = $node['children'];
    $hasChildren = count($children) > 0;
    $divisions = $employee->departments->pluck('name')->filter()->join(', ');
@endphp

<div data-org-node class="contents">
    <div class="org-card relative flex w-52 flex-col rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm">
        <div class="flex items-center gap-2.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[11px] font-semibold text-gray-700">{{ strtoupper(mb_substr($employee->full_name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1 text-left">
                <a href="{{ route('employees.show', $employee) }}" class="block truncate text-[13px] font-semibold text-gray-900 hover:text-primary hover:underline" title="{{ $employee->full_name }}">{{ $employee->full_name }}</a>
                <p class="truncate text-xs text-gray-500" title="{{ $employee->jobPosition?->name }}">{{ $employee->jobPosition?->name ?? 'Tanpa jabatan' }}</p>
            </div>
        </div>
        <p class="mt-1.5 truncate text-[11px] text-gray-400">{{ $divisions ?: '—' }}@if ($employee->branch) · {{ $employee->branch->name }}@endif</p>

        @if ($hasChildren)
            <button type="button" data-org-toggle aria-expanded="true" aria-label="{{ count($children) }} bawahan, buka atau tutup" class="absolute -bottom-3 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1 rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-500 shadow-sm transition hover:bg-gray-50 hover:text-gray-700">
                <span>{{ count($children) }}</span>
                <svg class="size-3 rotate-90 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <ul data-org-children>
            @foreach ($children as $child)
                <li>@include('organization.partials.node', ['node' => $child, 'depth' => $depth + 1])</li>
            @endforeach
        </ul>
    @endif
</div>
