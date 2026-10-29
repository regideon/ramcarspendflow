{{-- Vendor Goods Receipts (step 2), redesigned from Google Stitch. The old list stays at /hub/p2p/goods-receipts. --}}
@php
    use App\Filament\Pages\P2p\VendorGoodsReceipts;
    use App\Support\Peso;

    $receipts = $this->receipts;
    $counts = $this->tabCounts;
    $isVendor = $this->isVendor();
    $pageIds = $receipts->pluck('id')->map(fn ($id): string => (string) $id)->all();
    $allOnPageSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
@endphp

<x-filament-panels::page>
    <div class="space-y-5 pb-24">
        @include('filament.pages.p2p.vendor.steps', ['current' => 2])

        {{-- PO / GR scope chips --}}
        @if ($purchaseOrders || $grNumbers)
            <div class="flex flex-wrap items-center gap-2" data-tour="scope">
                <span class="me-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Purchase orders</span>
                @foreach ($purchaseOrders as $po)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 py-1 ps-2.5 pe-1.5 text-xs font-semibold text-primary-800 ring-1 ring-primary-200/70 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
                        <span class="font-mono">PO {{ $po }}</span>
                        <button type="button" wire:click="removePurchaseOrder(@js($po))" class="flex size-4 items-center justify-center rounded-full hover:bg-primary-200/70 dark:hover:bg-primary-500/20" title="Remove PO {{ $po }}">
                            <x-filament::icon icon="heroicon-m-x-mark" class="size-3" />
                        </button>
                    </span>
                @endforeach
                @foreach ($grNumbers as $gr)
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 font-mono text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">GR {{ $gr }}</span>
                @endforeach
                <button type="button" wire:click="resetScope" class="ms-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Reset</button>
            </div>
        @endif

        {{-- Tabs --}}
        <div class="overflow-x-auto pb-1" data-tour="tabs">
            <nav class="flex w-max items-center gap-1.5" role="tablist">
                @foreach (VendorGoodsReceipts::TABS as $key => $label)
                    @php $active = $tab === $key; @endphp
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $active ? 'true' : 'false' }}"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm transition',
                            'bg-primary-600 font-semibold text-white shadow-md shadow-primary-600/25' => $active,
                            'font-medium text-gray-500 hover:bg-white hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $active,
                        ])
                    >
                        {{ $label }}
                        <span @class(['rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold tabular-nums', 'bg-white/20 text-white' => $active, 'bg-gray-200/80 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $active])>{{ number_format($counts[$key]) }}</span>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Search --}}
        <div class="sf-card flex flex-col items-stretch justify-between gap-3 p-3.5 sm:flex-row sm:items-center">
            <label class="relative max-w-md flex-1">
                <span class="sr-only">Search</span>
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search GR no. or PO no." class="w-full rounded-xl border-0 bg-gray-50 py-2 ps-9 pe-3 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10" />
            </label>
            <span class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="size-2 animate-pulse rounded-full bg-primary-500"></span>
                <span class="tabular-nums">{{ number_format($receipts->total()) }} {{ str('goods receipt')->plural($receipts->total()) }}</span>
            </span>
        </div>

        {{-- Table --}}
        <section class="sf-card overflow-hidden" data-tour="table">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] border-collapse text-start text-sm">
                    <thead class="bg-gray-50/95 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                        <tr>
                            @if ($isVendor)
                                <th class="w-12 py-3.5 ps-6 pe-2 text-center">
                                    <input type="checkbox" aria-label="Select all on this page" @checked($allOnPageSelected) wire:click="togglePage(@js($pageIds))" class="size-4 cursor-pointer rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </th>
                            @endif
                            <th @class(['w-32 py-3.5 pe-3 text-start', 'ps-6' => ! $isVendor, 'ps-3' => $isVendor])>Action</th>
                            <th class="px-4 py-3.5 text-start">GR No.</th>
                            <th class="px-4 py-3.5 text-start">PO No.</th>
                            <th class="px-4 py-3.5 text-start">Status</th>
                            <th class="px-4 py-3.5 text-start">Vendor</th>
                            @unless ($isVendor)
                                <th class="px-4 py-3.5 text-end">Amount</th>
                            @endunless
                            <th class="py-3.5 ps-4 pe-6 text-start">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                        @forelse ($receipts as $receipt)
                            @php
                                $isSelected = in_array((string) $receipt->id, $selected, true);
                                $url = $this->processUrl($receipt);
                                $canProcess = $this->canProcess($receipt);
                            @endphp
                            <tr
                                wire:key="gr-{{ $receipt->id }}"
                                x-on:click="window.location = @js($url)"
                                @class(['group cursor-pointer transition', 'bg-primary-50/60 hover:bg-primary-50 dark:bg-primary-500/5' => $isSelected, 'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isSelected])
                            >
                                @if ($isVendor)
                                    <td class="py-3.5 ps-6 pe-2 text-center" x-on:click.stop>
                                        <input type="checkbox" value="{{ $receipt->id }}" wire:model.live="selected" aria-label="Select GR {{ $receipt->good_receipt_number }}" class="size-4 cursor-pointer rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    </td>
                                @endif
                                <td @class(['py-3.5 pe-3', 'ps-6' => ! $isVendor, 'ps-3' => $isVendor]) x-on:click.stop>
                                    @if ($canProcess)
                                        <a href="{{ $url }}" @class(['inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition', 'bg-primary-600 text-white shadow-sm hover:bg-primary-700' => $isSelected, 'bg-gray-100 text-gray-800 hover:bg-primary-600 hover:text-white dark:bg-white/10 dark:text-gray-200' => ! $isSelected])>
                                            Process
                                            <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5" />
                                        </a>
                                    @else
                                        <a href="{{ $url }}" class="text-xs font-semibold text-gray-500 hover:text-primary-600">View</a>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 font-mono font-bold">{{ $receipt->good_receipt_number }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 font-mono text-xs font-semibold text-primary-700 dark:text-primary-300">{{ $receipt->purchase_order }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($receipt->status)
                                        <x-sf.badge :color="$receipt->status->getColor() ?? 'gray'">{{ $receipt->status->getLabel() }}</x-sf.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary-50 text-[0.6875rem] font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ str($receipt->vendor?->name ?? '?')->substr(0, 1)->upper() }}</span>
                                        <span class="max-w-44 truncate font-medium">{{ $receipt->vendor?->name ?? '—' }}</span>
                                    </span>
                                </td>
                                @unless ($isVendor)
                                    <td class="whitespace-nowrap px-4 py-3.5 text-end font-mono font-bold tabular-nums">{{ $receipt->amount_total !== null ? Peso::format((float) $receipt->amount_total) : '—' }}</td>
                                @endunless
                                <td class="whitespace-nowrap py-3.5 ps-4 pe-6">
                                    <div class="text-xs font-medium">{{ $receipt->updated_at?->format('M j, Y') }}</div>
                                    <div class="text-[0.6875rem] text-gray-500">{{ $receipt->updated_at?->format('g:i A') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/10">
                                        <x-filament::icon icon="heroicon-o-inbox" class="size-6 text-gray-400" />
                                    </div>
                                    <p class="mt-3 font-display text-base font-semibold text-gray-900 dark:text-white">Nothing here yet</p>
                                    <p class="mt-1 text-xs text-gray-500">No goods receipts on this tab for the picked purchase orders.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-sf.pagination :paginator="$receipts" noun="goods receipts" />
        </section>
    </div>

    {{-- Floating bar while GRs are ticked --}}
    @if ($isVendor && $selected)
        <div class="fixed bottom-6 left-1/2 z-30 flex -translate-x-1/2 items-center gap-3 rounded-full bg-gray-900/95 py-2.5 ps-5 pe-3 text-white shadow-2xl backdrop-blur-xl dark:bg-gray-800/95">
            <span class="flex items-center gap-2 text-sm font-semibold">
                <span class="size-2.5 animate-ping rounded-full bg-success-400"></span>
                {{ count($selected) }} selected
            </span>
            <button type="button" wire:click="processSelected" class="flex items-center gap-1.5 rounded-full bg-primary-600 px-4 py-2 text-sm font-bold shadow-lg shadow-primary-600/40 transition hover:bg-primary-500">
                Process Selected
                <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
            </button>
            <button type="button" wire:click="$set('selected', [])" class="rounded-full px-3 py-1.5 text-sm text-gray-300 transition hover:text-white">Clear</button>
        </div>
    @endif

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
