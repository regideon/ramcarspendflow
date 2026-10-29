{{-- Bulk Accounts Payable, redesigned from Google Stitch (docs/stitch/p2p-bulk-accounts-payable/). --}}
@php
    use App\Enums\P2pInvoiceActionLabel;
    use Illuminate\Support\Facades\Storage;

    $record = $this->invoiceRecord();
    $files = collect($data['attachments'] ?? [])
        ->map(fn ($attachment) => is_array($attachment) ? ($attachment['path'] ?? null) : $attachment)
        ->filter(fn ($path) => is_string($path) && $path !== '')
        ->map(fn (string $path): array => [
            'url' => Storage::disk('public')->url($path),
            'name' => basename($path),
            'image' => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
        ])
        ->values();

    $matchSummary = $this->matchSummary();
    $flagCount = $record?->match_flag_count ?? 0;

    $peso = fn ($value): string => '₱'.number_format((float) str_replace(',', '', (string) ($value ?? 0)), 2);

    // Cancel goes left of the bar, everything else right (same actions as the old page).
    $actions = collect($this->getFooterActions());
    $back = $actions->first(fn ($action): bool => $action->getName() === 'back');
    $mainActions = $actions->reject(fn ($action): bool => $action->getName() === 'back')->values()->all();

    $summaryRows = [
        ['vatable_sales', 'Vatable Sales'],
        ['vat_exempt', 'VAT Exempt'],
        ['vat_zero_rated', 'VAT Zero Rated'],
        ['vat', 'VAT'],
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6 pb-24">
        {{-- Status, vendor and the two totals --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                @if ($record?->status)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-800 ring-1 ring-primary-200/70 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
                        <span class="size-2 animate-pulse rounded-full bg-warning-500"></span>
                        {{ $record->status->getLabel() }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-m-building-office-2" class="size-3.5 text-primary-600" />
                    {{ $data['vendor_name'] ?? '—' }}
                </span>
                @if (filled($data['grc_number'] ?? null))
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $data['grc_number'] }}</span>
                @endif
                @if ($isViewOnly)
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-white/10">View only</span>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <div class="flex flex-col items-end rounded-xl bg-white px-4 py-2 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Overall Invoice Total</span>
                    <span class="font-display text-lg font-bold tabular-nums text-gray-950 dark:text-white">{{ $peso($data['overall_invoice_total'] ?? 0) }}</span>
                </div>
                <div class="flex flex-col items-end rounded-xl bg-white px-4 py-2 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">NET</span>
                    <span class="font-display text-lg font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ $peso($data['net'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            {{-- LEFT: the vendor's invoice --}}
            <div data-tour="invoice" class="sf-card flex flex-col overflow-hidden lg:sticky lg:top-24 lg:col-span-5 lg:max-h-[calc(100vh-8rem)]" x-data="{ active: 0, zoom: 1 }">
                <div class="flex items-center justify-between gap-3 bg-gray-50/70 p-4 dark:bg-white/[0.02]">
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                            <x-filament::icon icon="heroicon-m-paper-clip" class="size-4" />
                        </span>
                        <div>
                            <span class="block text-sm font-semibold leading-tight text-gray-950 dark:text-white">Attached Invoice</span>
                            <span class="text-[0.6875rem] text-gray-500">{{ $files->count() }} {{ str('file')->plural($files->count()) }} from the vendor</span>
                        </div>
                    </div>
                    @if ($files->count() > 1)
                        <div class="flex items-center gap-1 rounded-lg bg-gray-200/60 p-1 dark:bg-white/10">
                            @foreach ($files as $index => $file)
                                <button
                                    type="button"
                                    x-on:click="active = {{ $index }}; zoom = 1"
                                    x-bind:class="active === {{ $index }} ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-800 dark:text-primary-300' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                                    class="rounded-md px-3 py-1 text-xs font-semibold transition"
                                >File {{ $index + 1 }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($files->isNotEmpty())
                    @foreach ($files as $index => $file)
                        <div x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif class="flex min-h-0 flex-1 flex-col">
                            <div class="flex items-center justify-between border-y border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-white/5">
                                <span class="truncate font-medium text-gray-700 dark:text-gray-300" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($file['image'])
                                        <button type="button" x-on:click="zoom = Math.max(0.5, zoom - 0.25)" class="flex size-7 items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-white/5" title="Zoom out"><x-filament::icon icon="heroicon-m-minus" class="size-4" /></button>
                                        <span class="w-10 text-center font-semibold tabular-nums text-gray-800 dark:text-gray-200" x-text="Math.round(zoom * 100) + '%'"></span>
                                        <button type="button" x-on:click="zoom = Math.min(3, zoom + 0.25)" class="flex size-7 items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-white/5" title="Zoom in"><x-filament::icon icon="heroicon-m-plus" class="size-4" /></button>
                                        <span class="mx-1 h-3.5 w-px bg-gray-200 dark:bg-white/10"></span>
                                    @endif
                                    <a href="{{ $file['url'] }}" download class="flex size-7 items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-white/5" title="Download"><x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4" /></a>
                                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="flex size-7 items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-white/5" title="Open in new tab"><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4" /></a>
                                </div>
                            </div>
                            <div class="min-h-0 flex-1 overflow-auto bg-gray-100/60 p-3 dark:bg-black/20">
                                @if ($file['image'])
                                    <img src="{{ $file['url'] }}" alt="Attached invoice {{ $index + 1 }}" class="mx-auto origin-top rounded-lg shadow-md transition-transform" x-bind:style="'transform: scale(' + zoom + ')'" />
                                @else
                                    {{-- The browser's PDF viewer brings page navigation and zoom --}}
                                    <iframe src="{{ $file['url'] }}#view=FitH" title="Attached invoice {{ $index + 1 }}" class="h-[70vh] w-full rounded-lg bg-white shadow-md" loading="lazy"></iframe>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
                        <x-filament::icon icon="heroicon-o-document" class="size-10 text-gray-300" />
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No invoice attached</p>
                    </div>
                @endif
            </div>

            {{-- RIGHT: the review --}}
            <div class="flex flex-col gap-6 lg:col-span-7">
                {{-- 1. Lines to check (Read invoice with AI) --}}
                @if ($matchSummary !== '')
                    <div data-tour="match-alert" class="rounded-2xl border-s-4 border-warning-500 bg-warning-50/90 p-5 shadow-sm dark:bg-warning-500/10">
                        <div class="flex items-start gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="font-display text-base font-bold text-gray-950 dark:text-white">
                                    {{ $flagCount > 0 ? $flagCount.' '.str('line')->plural($flagCount).' to check against the vendor\'s invoice' : 'Check the vendor\'s invoice' }}
                                </h2>
                                <div class="sf-match-summary mt-3 rounded-xl bg-white/70 p-3 text-sm text-gray-800 shadow-sm dark:bg-gray-900/60 dark:text-gray-200">{!! $matchSummary !!}</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- 2. Accounts Payable --}}
                <section data-tour="accounts-payable" class="sf-card p-6">
                    <x-sf.card-header heading="Accounts Payable" description="Review and validate the submitted Accounts Payable before forwarding them for approval." class="mb-5 border-0 pb-0" />
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach ([
                            ['Vendor', $data['vendor_name'] ?? '—', false],
                            ['Company', $data['company_name'] ?? '—', false],
                            ['Invoice Date', filled($data['invoice_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['invoice_at'])->format('M j, Y') : '—', false],
                            ['Reference No.', implode(', ', (array) ($data['reference_number'] ?? [])) ?: '—', true],
                        ] as [$label, $value, $mono])
                            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                                <span class="block text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</span>
                                <span @class(['mt-0.5 block font-semibold text-gray-950 dark:text-white', 'font-mono text-sm' => $mono])>{{ $value }}</span>
                            </div>
                        @endforeach
                        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                            <span class="block text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Overall Invoice Total</span>
                            <span class="mt-0.5 block font-display text-lg font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ $peso($data['overall_invoice_total'] ?? 0) }}</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                            <span class="block text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">Invoice No.</span>
                            @if (filled($data['invoice_number'] ?? null))
                                <span class="mt-0.5 block font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $data['invoice_number'] }}</span>
                            @else
                                <span class="mt-0.5 block text-sm italic text-gray-500">SAP-populated on posting</span>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- 3. Items --}}
                <section data-tour="items" class="sf-card p-6">
                    <x-sf.card-header heading="Items" description="The items selected for purchase" class="mb-4 border-0 pb-0" />

                    <div class="space-y-5">
                        @forelse ($data['goods_receipts'] ?? [] as $g => $gr)
                            @php
                                $items = $gr['goodReceiptItems'] ?? [];
                                $num = fn ($value): float => (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
                            @endphp
                            <div wire:key="gr-{{ $g }}">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">Goods Receipt Number {{ $gr['good_receipt_number'] ?? '—' }}</span>
                                    <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-800 dark:bg-primary-500/10 dark:text-primary-300">Purchase Order Number {{ $gr['purchase_order'] ?? '—' }}</span>
                                    @if ($gr['is_escalated'] ?? false)
                                        <x-filament::badge color="warning">Escalated</x-filament::badge>
                                    @endif
                                </div>
                                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                                    <table class="w-full min-w-[40rem] text-start text-xs">
                                        <thead>
                                            <tr class="bg-gray-50 text-[0.6875rem] font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                                @if ($this->showsReportColumn())
                                                    <th class="px-3 py-3 text-center">Report</th>
                                                @endif
                                                @if ($showEscalationColumn)
                                                    <th class="px-3 py-3 text-start">Scope</th>
                                                @endif
                                                <th class="px-3 py-3 text-start">Short Text</th>
                                                <th class="px-2 py-3 text-center">Item No.</th>
                                                <th class="px-2 py-3 text-end">PO Qty</th>
                                                <th class="px-2 py-3 text-center">Uom</th>
                                                <th class="px-2 py-3 text-end">Invoice Qty</th>
                                                <th class="px-3 py-3 text-end">UC</th>
                                                <th class="px-3 py-3 text-end">Amount</th>
                                                <th class="px-3 py-3 text-center">3-Way</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
                                            @foreach ($items as $i => $item)
                                                @php
                                                    $match = is_array($item['match_detail'] ?? null) ? $item['match_detail'] : [];
                                                    $notMatched = ($match['status'] ?? null) === 'no_match';
                                                @endphp
                                                <tr wire:key="gr-{{ $g }}-item-{{ $i }}" @class(['transition', 'bg-danger-50/60 dark:bg-danger-500/5' => $notMatched, 'hover:bg-gray-50 dark:hover:bg-white/5' => ! $notMatched])>
                                                    @if ($this->showsReportColumn())
                                                        <td class="px-3 py-3 text-center">
                                                            <input type="checkbox" wire:model="data.goods_receipts.{{ $g }}.goodReceiptItems.{{ $i }}.escalate" aria-label="Report this line" class="size-4 cursor-pointer rounded border-gray-300 text-warning-600 focus:ring-warning-500" />
                                                        </td>
                                                    @endif
                                                    @if ($showEscalationColumn)
                                                        <td class="px-3 py-3">
                                                            <x-filament::badge :color="($item['escalation_status'] ?? '') === 'Escalated' ? 'warning' : 'gray'" size="sm">{{ $item['escalation_status'] ?? '—' }}</x-filament::badge>
                                                        </td>
                                                    @endif
                                                    <td class="px-3 py-3">
                                                        <div @class(['font-medium', 'text-danger-900 dark:text-danger-300' => $notMatched, 'text-gray-950 dark:text-white' => ! $notMatched])>{{ $item['item_description'] ?? '—' }}</div>
                                                        @if ($notMatched && filled($match['reason'] ?? null))
                                                            <span class="mt-0.5 block text-[0.6875rem] text-danger-700 dark:text-danger-400">{{ $match['reason'] }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-3 text-center font-mono text-gray-500">{{ $item['purchase_order_item'] ?? '—' }}</td>
                                                    <td class="px-2 py-3 text-end font-mono font-medium tabular-nums">{{ $item['quantity_po'] ?? '—' }}</td>
                                                    <td class="px-2 py-3 text-center text-gray-500">{{ $item['uom'] ?? '—' }}</td>
                                                    <td class="px-2 py-3 text-end font-mono font-medium tabular-nums">{{ $item['quantity_served'] ?? '—' }}</td>
                                                    <td class="px-3 py-3 text-end font-mono tabular-nums">
                                                        @if ($notMatched)
                                                            <span class="cursor-help rounded bg-danger-100 px-1.5 py-0.5 font-semibold text-danger-700 dark:bg-danger-500/15 dark:text-danger-300" title="{{ $match['reason'] ?? 'Not matched' }}">{{ $item['unit_cost'] ?? '—' }}</span>
                                                        @else
                                                            {{ $item['unit_cost'] ?? '—' }}
                                                        @endif
                                                    </td>
                                                    <td @class(['px-3 py-3 text-end font-mono font-semibold tabular-nums', 'text-danger-900 dark:text-danger-300' => $notMatched, 'text-gray-950 dark:text-white' => ! $notMatched])>{{ $item['amount'] ?? '—' }}</td>
                                                    <td class="px-3 py-3 text-center">
                                                        @include('filament.components.three-way-match-badge', $match)
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-gray-50 text-xs font-semibold text-gray-900 dark:bg-white/5 dark:text-white">
                                                <td colspan="{{ 8 + ($this->showsReportColumn() ? 1 : 0) + ($showEscalationColumn ? 1 : 0) }}" class="px-3 py-3 text-end">
                                                    <span class="me-4 font-medium text-gray-500">Total PO Qty: <span class="tabular-nums text-gray-900 dark:text-white">{{ number_format(collect($items)->sum(fn ($item): float => $num($item['quantity_po'] ?? 0))) }}</span></span>
                                                    <span class="me-4 font-medium text-gray-500">Total Invoice Qty: <span class="tabular-nums text-gray-900 dark:text-white">{{ number_format(collect($items)->sum(fn ($item): float => $num($item['quantity_served'] ?? 0))) }}</span></span>
                                                    <span class="font-medium text-gray-500">Total Amount:</span>
                                                    <span class="ms-1 font-mono font-bold tabular-nums">{{ $currencySymbol }} {{ number_format(collect($items)->sum(fn ($item): float => $num($item['amount'] ?? 0)), 2) }}</span>
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-gray-500">No items on this submission.</p>
                        @endforelse
                    </div>
                </section>

                {{-- 4. RFP Down Payments --}}
                <section data-tour="rfp-down-payments" class="sf-card p-6">
                    <x-sf.card-header heading="RFP Down Payments" description="Link the vendor's paid RFPs, usually supplier down payments, to this invoice. For reference only: no amounts or entries change." class="mb-4 border-0 pb-0" />
                    @if ($this->canLinkRfps())
                        <div class="mb-4">{{ $this->rfpForm }}</div>
                    @endif
                    <div class="overflow-x-auto">{!! $this->linkedRfpsTable() !!}</div>
                </section>

                {{-- 5. Tax + Summary --}}
                <div data-tour="tax" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <section class="sf-card p-6">
                        <x-sf.card-header heading="Tax" class="mb-4 border-0 pb-0" />
                        {{ $this->form }}
                    </section>

                    <section class="sf-card flex flex-col justify-between p-6">
                        <div>
                            <x-sf.card-header heading="Summary" class="mb-4 border-0 pb-0">
                                <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">{{ $currencyKey }}</span>
                            </x-sf.card-header>
                            <div class="space-y-3 text-sm">
                                @foreach ($summaryRows as [$key, $label])
                                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                        <span>{{ $label }}</span>
                                        <span class="font-mono font-medium tabular-nums text-gray-900 dark:text-white">{{ $peso($data[$key] ?? 0) }}</span>
                                    </div>
                                @endforeach
                                <div class="flex items-center justify-between border-t border-gray-100 pt-2 font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                                    <span>Total</span>
                                    <span class="font-mono tabular-nums">{{ $peso($data['total'] ?? 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                    <span>EWT</span>
                                    <span class="font-mono font-medium tabular-nums">−{{ $peso($data['ewt'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex items-baseline justify-between gap-3 border-t-2 border-gray-100 pt-4 dark:border-white/10">
                            <span class="text-sm font-bold uppercase tracking-wider text-gray-500">NET</span>
                            <span class="font-display text-3xl font-bold tracking-tight tabular-nums text-gray-950 dark:text-white">{{ $peso($data['net'] ?? 0) }}</span>
                        </div>
                    </section>
                </div>

                {{-- 6. Approval History --}}
                <section class="sf-card p-6">
                    <x-sf.card-header heading="Approval History" description="All actions and remarks related to this goods receipt" class="mb-5 border-0 pb-0" />
                    @include('filament.components.approval-history-timeline', ['record' => $record])
                </section>
            </div>
        </div>
    </div>

    {{-- Sticky action bar: same actions as the old page --}}
    <div data-tour="actions" class="sticky bottom-0 z-20 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3.5 shadow-[0_-8px_24px_rgba(15,23,42,0.06)] backdrop-blur-xl sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-white/10 dark:bg-gray-900/95">
        {{-- pe keeps the actions clear of the floating Guide pill and Messages bubble --}}
        <div class="flex flex-wrap items-center justify-between gap-3 sm:pe-48">
            <div>
                @if ($back)
                    {{ $back }}
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @foreach (array_reverse($mainActions) as $action)
                    {{ $action }}
                @endforeach
            </div>
        </div>
    </div>

    <x-filament-actions::modals />

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
