{{-- Search box with ready-made suggestions. ⌘K / Ctrl+K focuses it. --}}
<section
    data-tour="search"
    class="sf-card space-y-3 p-4"
    x-data
    x-on:keydown.window.prevent.cmd.k="$refs.search.focus()"
    x-on:keydown.window.prevent.ctrl.k="$refs.search.focus()"
>
    <div class="flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-2.5 ring-1 ring-gray-200 transition focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/40 dark:bg-white/5 dark:ring-white/10 dark:focus-within:bg-gray-900">
        <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-tr from-primary-700 to-primary-500 text-white shadow-sm">
            <x-filament::icon icon="heroicon-m-sparkles" class="size-4" />
        </div>
        <input
            x-ref="search"
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by RFP no., invoice no. or payee — e.g. RFP-2026-000127, Meralco"
            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white"
        />
        <div wire:loading.delay wire:target="search" class="shrink-0">
            <x-filament::loading-indicator class="size-4 text-primary-500" />
        </div>
        <kbd class="hidden shrink-0 rounded bg-white px-2 py-0.5 text-[0.6875rem] font-semibold text-gray-500 shadow-sm ring-1 ring-gray-200 sm:inline-flex dark:bg-gray-800 dark:ring-white/10">⌘K</kbd>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="me-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Suggestions</span>
        @foreach ($this->suggestions() as $suggestion)
            <button
                type="button"
                wire:click="pickFocus('{{ $suggestion->key }}')"
                @class([
                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-medium transition',
                    'bg-primary-600 text-white shadow-sm' => $focus === $suggestion->key,
                    'bg-primary-50 text-gray-800 hover:bg-primary-100 hover:text-primary-800 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $focus !== $suggestion->key,
                ])
            >
                <x-filament::icon icon="heroicon-m-sparkles" @class(['size-3.5', 'text-white' => $focus === $suggestion->key, 'text-primary-500' => $focus !== $suggestion->key]) />
                {{ $suggestion->label }}
                @if ($focus === $suggestion->key)
                    <x-filament::icon icon="heroicon-m-x-mark" class="size-3.5" />
                @endif
            </button>
        @endforeach
    </div>
</section>
