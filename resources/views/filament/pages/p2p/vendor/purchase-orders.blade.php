{{-- Vendor Purchase Orders (step 1), redesigned from Google Stitch. The old list stays at /hub/p2p/purchase-orders. --}}
@php
    use App\Filament\Pages\P2p\VendorPurchaseOrders;

    $purchaseOrders = $this->purchaseOrders;
    $counts = $this->tabCounts;
    $isVendor = $this->canProcess();
    $pageIds = $purchaseOrders->pluck('purchase_order')->map(fn ($po): string => (string) $po)->all();
    $allOnPageSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
    $hasFilters = filled($search) || filled($status);
    $sortIcon = fn (string $column): string => $sort === $column ? ($direction === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down') : 'heroicon-m-chevron-up-down';
@endphp

<x-filament-panels::page>
    <div class="space-y-5 pb-24">
        @include('filament.pages.p2p.vendor.steps', ['current' => 1])

        <section class="sf-card flex flex-col overflow-hidden">
            <div class="flex flex-col gap-4 p-5">
                {{-- Tabs --}}
                <div class="flex flex-wrap items-center justify-between gap-3" data-tour="tabs">
                    <nav class="flex flex-wrap items-center gap-1.5 rounded-full bg-gray-100/80 p-1 dark:bg-white/5" role="tablist">
                        @foreach (VendorPurchaseOrders::TABS as $key => $label)
                            @php $active = $tab === $key; @endphp
                            <button
                                type="button"
                                role="tab"
                                aria-selected="{{ $active ? 'true' : 'false' }}"
                                wire:click="setTab('{{ $key }}')"
                                @class([
                                    'flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-1.5 text-sm transition',
                                    'bg-white font-semibold text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' => $active,
                                    'font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => ! $active,
                                ])
                            >
                                {{ $label }}
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.6875rem] font-bold tabular-nums',
                                    'bg-warning-100 text-warning-800 dark:bg-warning-500/15 dark:text-warning-300' => $key === 'pending_and_partial',
                                    'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300' => $key === 'fully_submitted' || $key === 'all',
                                    'bg-success-100 text-success-800 dark:bg-success-500/15 dark:text-success-300' => $key === 'completed',
                                ])>{{ number_format($counts[$key]) }}</span>
                            </button>
                        @endforeach
                    </nav>
                    <span class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="size-2 animate-pulse rounded-full bg-primary-500"></span>
                        <span class="tabular-nums">{{ number_format($purchaseOrders->total()) }} {{ str('result')->plural($purchaseOrders->total()) }}</span>
                    </span>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap items-center gap-2">
                    <label class="relative min-w-[16rem] max-w-md flex-1">
                        <span class="sr-only">Search</span>
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search PO no. or vendor" class="w-full rounded-xl border-0 bg-gray-50 py-2 ps-9 pe-3 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10" />
                    </label>
                    <select wire:model.live="status" aria-label="Status" class="rounded-xl border-0 bg-gray-50 py-2 ps-3 pe-8 text-xs font-medium text-gray-800 ring-1 ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                        <option value="">Status: All</option>
                        @foreach ($this->statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($hasFilters)
                        <button type="button" wire:click="clearFilters" class="px-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Clear all</button>
                    @endif
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto" data-tour="table">
                <table class="w-full min-w-[60rem] border-collapse text-start text-sm">
                    <thead class="bg-gray-50/95 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                        <tr>
                            @if ($isVendor)
                                <th class="w-12 py-3 ps-6 pe-2 text-center">
                                    <input type="checkbox" aria-label="Select all on this page" @checked($allOnPageSelected) wire:click="togglePage(@js($pageIds))" class="size-4 cursor-pointer rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </th>
                            @endif
                            <th @class(['w-36 py-3 pe-3 text-start', 'ps-6' => ! $isVendor, 'ps-3' => $isVendor])>Action</th>
                            @foreach ([['purchase_order', 'PO Number', 'text-start'], ['vendor', 'Vendor', 'text-start'], ['company', 'Company', 'text-start'], ['purchase_order_date', 'PO date', 'text-start'], ['status', 'Status', 'text-start'], ['quantity_po_balance', 'PO balance', 'text-end pe-6']] as [$column, $label, $align])
                                @continue($column === 'vendor' && $isVendor)
                                <th class="whitespace-nowrap px-3 py-3 {{ $align }}">
                                    @if (in_array($column, VendorPurchaseOrders::SORTABLE, true))
                                        <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-900 dark:hover:text-white">
                                            {{ $label }}
                                            <x-filament::icon :icon="$sortIcon($column)" @class(['size-3.5', 'text-primary-600' => $sort === $column, 'opacity-40' => $sort !== $column]) />
                                        </button>
                                    @else
                                        {{ $label }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                        @forelse ($purchaseOrders as $po)
                            @php
                                $isSelected = in_array((string) $po->purchase_order, $selected, true);
                                $url = $this->goodsReceiptsUrl($po);
                                $statusEnum = $po->status_id instanceof \App\Enums\P2pPurchaseOrderStatus ? $po->status_id : \App\Enums\P2pPurchaseOrderStatus::tryFrom((int) $po->status_id);
                            @endphp
                            <tr
                                wire:key="po-{{ $po->id }}"
                                x-on:click="window.location = @js($url)"
                                @class(['group cursor-pointer transition', 'bg-primary-50/60 hover:bg-primary-50 dark:bg-primary-500/5' => $isSelected, 'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isSelected])
                            >
                                @if ($isVendor)
                                    <td class="py-3 ps-6 pe-2 text-center" x-on:click.stop>
                                        <input type="checkbox" value="{{ $po->purchase_order }}" wire:model.live="selected" aria-label="Select PO {{ $po->purchase_order }}" class="size-4 cursor-pointer rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    </td>
                                @endif
                                <td @class(['py-3 pe-3', 'ps-6' => ! $isVendor, 'ps-3' => $isVendor]) x-on:click.stop>
                                    <a href="{{ $url }}" @class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition', 'bg-primary-100 text-primary-800 hover:bg-primary-200 dark:bg-primary-500/20 dark:text-primary-200' => $isSelected, 'bg-gray-100 text-primary-700 hover:bg-primary-600 hover:text-white dark:bg-white/10 dark:text-primary-300' => ! $isSelected])>
                                        <x-filament::icon icon="heroicon-m-clipboard-document-check" class="size-4" />
                                        View GR's
                                    </a>
                                </td>
                                <td @class(['whitespace-nowrap px-3 py-3 font-mono text-base font-bold', 'text-primary-700 dark:text-primary-300' => $isSelected])>{{ $po->purchase_order }}</td>
                                @unless ($isVendor)
                                    <td class="px-3 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $po->vendor_code }}</td>
                                @endunless
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-filament::icon icon="heroicon-m-building-office-2" class="size-4 text-gray-400" />
                                        {{ $po->company?->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $po->purchase_order_date?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    @if ($statusEnum)
                                        <x-sf.badge :color="$statusEnum->getColor()">{{ $statusEnum->getLabel() }}</x-sf.badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap py-3 ps-3 pe-6 text-end font-display text-base font-bold tabular-nums">{{ is_numeric($po->quantity_po_balance) ? number_format((float) $po->quantity_po_balance) : ($po->quantity_po_balance ?? '—') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/10">
                                        <x-filament::icon icon="heroicon-o-inbox" class="size-6 text-gray-400" />
                                    </div>
                                    <p class="mt-3 font-display text-base font-semibold text-gray-900 dark:text-white">Nothing here yet</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        @if ($hasFilters)
                                            Try another tab or <button type="button" wire:click="clearFilters" class="font-semibold text-primary-600 hover:underline">clear the filters</button>.
                                        @else
                                            Your purchase orders from Ramcar appear here. Use Sync from SAP to fetch the latest.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-sf.pagination :paginator="$purchaseOrders" />
        </section>
    </div>

    {{-- Floating bar while POs are ticked --}}
    @if ($isVendor && $selected)
        <div class="fixed bottom-6 left-1/2 z-30 flex -translate-x-1/2 items-center gap-4 rounded-2xl bg-gray-900/95 px-5 py-3 text-white shadow-2xl backdrop-blur-xl dark:bg-gray-800/95">
            <span class="flex items-center gap-2 text-sm">
                <span class="size-2.5 animate-ping rounded-full bg-success-400"></span>
                <strong class="font-display text-base">{{ count($selected) }}</strong> selected
            </span>
            <span class="h-5 w-px bg-white/20"></span>
            <button type="button" wire:click="processGrs" class="flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold shadow-lg shadow-primary-600/40 transition hover:bg-primary-500">
                Process GRs
                <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
            </button>
            <button type="button" wire:click="$set('selected', [])" class="rounded-xl px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">Clear</button>
        </div>
    @endif

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
