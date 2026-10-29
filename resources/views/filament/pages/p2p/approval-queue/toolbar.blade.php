@php
    use App\Filament\Pages\P2p\ApprovalQueue;

    $counts = $this->tabCounts;
    $hasFilters = filled($search) || filled($status) || $needsReview;
    $oldestFirst = $sort === 'submitted_at' && $direction === 'asc';
    $total = $this->invoices->total();
@endphp

{{-- Tabs: the old list's queues --}}
<div class="overflow-x-auto pb-1" data-tour="tabs">
    <nav class="flex min-w-max items-center gap-1.5 rounded-2xl bg-gray-100/80 p-1 dark:bg-white/5" role="tablist">
        @foreach (ApprovalQueue::TABS as $key => $item)
            @php
                $active = $tab === $key;
                $badge = match (true) {
                    $active => 'bg-white text-primary-700',
                    $key === 'escalated' && $counts[$key] > 0 => 'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300',
                    $key === 'posted' && $counts[$key] > 0 => 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300',
                    default => 'bg-gray-200/80 text-gray-600 dark:bg-white/10 dark:text-gray-300',
                };
                $iconColor = match (true) {
                    $active => 'text-white',
                    $key === 'escalated' => 'text-danger-500',
                    $key === 'posted' => 'text-success-600',
                    default => 'text-gray-400',
                };
            @endphp
            <button
                type="button"
                role="tab"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                wire:click="setTab('{{ $key }}')"
                @class([
                    'flex items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm transition',
                    'bg-primary-600 font-semibold text-white shadow-sm' => $active,
                    'font-medium text-gray-600 hover:bg-white/80 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white' => ! $active,
                ])
            >
                <x-filament::icon :icon="$item['icon']" @class(['size-4', $iconColor]) />
                {{ $item['label'] }}
                <span @class(['rounded-full px-2 py-0.5 text-[0.6875rem] font-bold tabular-nums', $badge])>{{ number_format($counts[$key]) }}</span>
            </button>
        @endforeach
    </nav>
</div>

{{-- Filters --}}
<div class="sf-card flex flex-col items-stretch justify-between gap-3 p-4 lg:flex-row lg:items-center" data-tour="filters">
    <div class="flex flex-1 flex-wrap items-center gap-2">
        <label class="relative min-w-[16rem] max-w-md flex-1">
            <span class="sr-only">Search</span>
            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="Search GRC no., invoice no. or vendor"
                class="w-full rounded-xl border-0 bg-gray-50 py-2 ps-9 pe-3 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10"
            />
        </label>

        <select
            wire:model.live="status"
            aria-label="Status"
            class="rounded-xl border-0 bg-gray-50 py-2 ps-3 pe-8 text-xs font-medium text-gray-800 ring-1 ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10"
        >
            <option value="">Status: All</option>
            @foreach ($this->statusOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        <button
            type="button"
            wire:click="toggleNeedsReview"
            aria-pressed="{{ $needsReview ? 'true' : 'false' }}"
            @class([
                'inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition',
                'bg-primary-100 text-primary-800 ring-1 ring-primary-200 dark:bg-primary-500/15 dark:text-primary-200 dark:ring-primary-500/30' => $needsReview,
                'bg-gray-50 text-gray-600 ring-1 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! $needsReview,
            ])
        >
            <x-filament::icon icon="heroicon-m-funnel" @class(['size-4', 'text-primary-600' => $needsReview, 'text-gray-400' => ! $needsReview]) />
            Needs invoice review
            @if ($needsReview)
                <span class="size-2 animate-pulse rounded-full bg-primary-500"></span>
            @endif
        </button>

        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="px-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Clear all</button>
        @endif
    </div>

    <div class="flex items-center justify-between gap-3 text-xs text-gray-500 lg:justify-end dark:text-gray-400">
        <button type="button" wire:click="toggleOldestFirst" class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white">
            <x-filament::icon icon="heroicon-m-bars-arrow-down" class="size-4" />
            Sorted by: <strong class="font-semibold text-gray-800 dark:text-gray-100">{{ $sort === 'submitted_at' ? ($oldestFirst ? 'Oldest first' : 'Newest first') : 'Column' }}</strong>
        </button>
        <span class="hidden h-4 w-px bg-gray-200 sm:block dark:bg-white/10"></span>
        <span class="font-medium tabular-nums">{{ number_format($total) }} of {{ number_format($counts[$tab] ?? 0) }} matching</span>
        <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="export" data-tour="export">
            Export
        </x-filament::button>
    </div>
</div>
