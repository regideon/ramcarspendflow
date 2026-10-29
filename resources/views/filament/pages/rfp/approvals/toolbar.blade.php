@php
    use App\Filament\Pages\Rfp\Approvals;

    $options = $this->filterOptions;
    $active = $this->activeFilters;
    $fieldClasses = 'w-full rounded-lg border-0 bg-gray-50 py-1.5 ps-3 pe-8 text-xs text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10';
    $buttonClasses = 'inline-flex items-center gap-2 rounded-lg bg-gray-50 px-3.5 py-2 text-xs font-semibold text-gray-800 ring-1 ring-gray-200 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10';
@endphp

<div
    data-tour="toolbar"
    class="flex flex-col gap-2.5 border-b border-gray-100 px-4 py-3.5 dark:border-white/5"
    x-on:keydown.window.prevent.cmd.k="$refs.search.focus()"
    x-on:keydown.window.prevent.ctrl.k="$refs.search.focus()"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        {{-- Search --}}
        <div class="flex min-w-64 max-w-xl flex-1 items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-200 transition focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/40 dark:bg-white/5 dark:ring-white/10 dark:focus-within:bg-gray-900">
            <x-filament::icon icon="heroicon-m-magnifying-glass" class="size-4 shrink-0 text-gray-400" />
            <input
                x-ref="search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search RFP ID, invoice no., payee, vendor or requestor"
                class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white"
            />
            <div wire:loading.delay wire:target="search" class="shrink-0">
                <x-filament::loading-indicator class="size-4 text-primary-500" />
            </div>
            <kbd class="hidden shrink-0 rounded bg-white px-1.5 py-0.5 text-[0.6875rem] font-semibold text-gray-500 shadow-sm ring-1 ring-gray-200 sm:inline-flex dark:bg-gray-800 dark:ring-white/10">⌘K</kbd>
        </div>

        <div class="flex items-center gap-2">
            {{-- Filters --}}
            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                <button type="button" x-on:click="open = ! open" class="{{ $buttonClasses }}">
                    <x-filament::icon icon="heroicon-m-funnel" class="size-4 text-primary-600 dark:text-primary-400" />
                    Filters
                    @if (count($active))
                        <span class="flex size-5 items-center justify-center rounded-full bg-primary-600 text-[0.6875rem] font-bold text-white">{{ count($active) }}</span>
                    @endif
                </button>

                <div x-cloak x-show="open" x-transition.origin.top.right class="absolute end-0 z-30 mt-2 w-80 space-y-3 rounded-xl bg-white p-4 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <label class="block space-y-1">
                        <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Company</span>
                        <select wire:model.live="company" class="{{ $fieldClasses }}">
                            <option value="">All companies</option>
                            @foreach ($options['companies'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block space-y-1">
                        <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Vendor</span>
                        <select wire:model.live="vendor" class="{{ $fieldClasses }}">
                            <option value="">All vendors</option>
                            @foreach ($options['vendors'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block space-y-1">
                            <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Urgency</span>
                            <select wire:model.live="dueType" class="{{ $fieldClasses }}">
                                <option value="">Any</option>
                                @foreach ($options['dueTypes'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">RFP type</span>
                            <select wire:model.live="type" class="{{ $fieldClasses }}">
                                <option value="">Any</option>
                                @foreach ($options['types'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <fieldset class="space-y-1">
                        <legend class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Status</legend>
                        <div class="grid max-h-36 grid-cols-1 gap-1 overflow-y-auto rounded-lg bg-gray-50 p-2 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                            @foreach ($options['statuses'] as $value => $label)
                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" value="{{ $value }}" wire:model.live="statuses" class="size-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block space-y-1">
                            <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Start date</span>
                            <input type="date" wire:model.live="from" class="{{ $fieldClasses }} pe-2" />
                        </label>
                        <label class="block space-y-1">
                            <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">End date</span>
                            <input type="date" wire:model.live="until" class="{{ $fieldClasses }} pe-2" />
                        </label>
                    </div>
                </div>
            </div>

            {{-- Columns --}}
            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                <button type="button" x-on:click="open = ! open" class="{{ $buttonClasses }}">
                    <x-filament::icon icon="heroicon-m-view-columns" class="size-4" />
                    Columns
                </button>
                <div x-cloak x-show="open" x-transition.origin.top.right class="absolute end-0 z-30 mt-2 w-56 space-y-1 rounded-xl bg-white p-3 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    @foreach (Approvals::COLUMNS as $column => $label)
                        <label class="flex items-center gap-2 rounded px-1.5 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                            <input type="checkbox" x-bind:checked="shows('{{ $column }}')" x-on:change="toggle('{{ $column }}')" class="size-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="button" wire:click="export" title="Export CSV" class="{{ $buttonClasses }} !px-2.5">
                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4" />
                <span class="sr-only">Export CSV</span>
            </button>
        </div>
    </div>

    {{-- Filters in use --}}
    @if (count($active))
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="me-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Active filters:</span>
                @foreach ($active as $filter => ['label' => $label, 'value' => $value])
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs text-gray-900 dark:bg-white/10 dark:text-white">
                        <span class="text-gray-500 dark:text-gray-400">{{ $label }}:</span>
                        <span class="max-w-64 truncate font-medium">{{ $value }}</span>
                        <button type="button" wire:click="removeFilter('{{ $filter }}')" class="text-gray-400 hover:text-gray-900 dark:hover:text-white" aria-label="Remove {{ $label }} filter">
                            <x-filament::icon icon="heroicon-m-x-mark" class="size-3.5" />
                        </button>
                    </span>
                @endforeach
            </div>
            <button type="button" wire:click="clearFilters" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400">Clear all</button>
        </div>
    @endif
</div>
