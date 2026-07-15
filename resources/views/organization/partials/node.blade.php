@php
    /** @var \App\Models\Employee $employee */
    $employee = $node['employee'];
    $children = $node['children'];
    $hasChildren = count($children) > 0;
    $divisions = $employee->departments->pluck('name')->filter()->join(', ');
@endphp

<div data-org-node class="org-node">
    <div class="flex items-center gap-1.5 py-1">
        @if ($hasChildren)
            <button type="button" data-org-toggle aria-expanded="true" aria-label="Buka/tutup bawahan" class="flex size-5 shrink-0 items-center justify-center rounded text-gray-400 transition rotate-90 hover:bg-gray-100 hover:text-gray-600">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
        @else
            <span class="size-5 shrink-0"></span>
        @endif

        <div class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-xs">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[11px] font-semibold text-gray-700">{{ strtoupper(mb_substr($employee->full_name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <a href="{{ route('employees.show', $employee) }}" class="block truncate text-[13px] font-semibold text-gray-900 hover:text-primary hover:underline">{{ $employee->full_name }}</a>
                <p class="truncate text-xs text-gray-500">{{ $employee->jobPosition?->name ?? 'Tanpa jabatan' }}</p>
                <p class="truncate text-[11px] text-gray-400">{{ $divisions ?: '—' }}@if ($employee->branch) · {{ $employee->branch->name }}@endif</p>
            </div>
            @if ($hasChildren)
                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ count($children) }} bawahan</span>
            @endif
        </div>
    </div>

    @if ($hasChildren)
        <div data-org-children class="ml-[1.4rem] space-y-0.5 border-l border-gray-200 pl-4">
            @foreach ($children as $child)
                @include('organization.partials.node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
