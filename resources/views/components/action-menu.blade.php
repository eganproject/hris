{{-- Compact "kebab" dropdown for row actions. Put action items (links / delete
     forms) in the slot, styled with the .action-menu-item class. --}}
<div class="relative inline-block text-left" data-dropdown>
    <button type="button" data-dropdown-trigger aria-haspopup="true" aria-expanded="false"
        class="inline-flex size-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/20">
        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="12" cy="5" r="1.6"></circle>
            <circle cx="12" cy="12" r="1.6"></circle>
            <circle cx="12" cy="19" r="1.6"></circle>
        </svg>
        <span class="sr-only">Aksi</span>
    </button>
    <div data-dropdown-menu hidden
        class="w-44 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5">
        {{ $slot }}
    </div>
</div>
