{{-- Floating bar while rows are selected. --}}
@if ($selected)
    <div class="fixed bottom-8 left-1/2 z-30 flex -translate-x-1/2 items-center gap-4 rounded-full bg-gray-900/95 px-5 py-2.5 text-white shadow-2xl backdrop-blur-xl dark:bg-gray-800/95">
        <span class="flex items-center gap-2 text-xs font-bold tracking-wide">
            <span class="size-2 animate-ping rounded-full bg-success-400"></span>
            {{ count($selected) }} {{ str('request')->plural(count($selected)) }} selected
        </span>
        <span class="h-4 w-px bg-white/20"></span>
        <div class="flex items-center gap-1 text-xs font-medium">
            <button type="button" wire:click="exportSelected" class="flex items-center gap-1.5 rounded-full px-3 py-1 transition hover:bg-white/10">
                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4" />
                Export
            </button>
            <button
                type="button"
                wire:click="deleteSelectedDrafts"
                wire:confirm="Delete the selected drafts? Submitted requests are not affected."
                class="flex items-center gap-1.5 rounded-full px-3 py-1 text-danger-300 transition hover:bg-danger-500/20"
            >
                <x-filament::icon icon="heroicon-m-trash" class="size-4" />
                Delete drafts
            </button>
        </div>
        <span class="h-4 w-px bg-white/20"></span>
        <button type="button" wire:click="$set('selected', [])" class="flex items-center gap-1 text-[0.6875rem] uppercase tracking-wider text-gray-400 transition hover:text-white">
            <x-filament::icon icon="heroicon-m-x-mark" class="size-3.5" />
            Clear
        </button>
    </div>
@endif
