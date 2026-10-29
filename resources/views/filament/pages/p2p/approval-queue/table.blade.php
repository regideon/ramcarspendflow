@php
    use App\Filament\Pages\P2p\ApprovalQueue;
    use App\Filament\Resources\Rfps\RfpResource;
    use App\Support\Peso;

    $invoices = $this->invoices;
    $hasFilters = filled($search) || filled($status) || $needsReview;

    // Badge look per Filament color (status, 3-way match)
    $badge = fn (string $color): string => match ($color) {
        'success' => 'bg-success-100 text-success-800 dark:bg-success-500/15 dark:text-success-300',
        'warning' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
        'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300',
        'info', 'primary' => 'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300',
        default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
    };
    $dot = fn (string $color): string => match ($color) {
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        'info', 'primary' => 'bg-primary-500',
        default => 'bg-gray-400',
    };

    $sortIcon = fn (string $column): string => $sort === $column
        ? ($direction === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down')
        : 'heroicon-m-chevron-up-down';

    $columns = [
        ['grc', 'GRC No.', 'text-start'],
        ['vendor', 'Vendor', 'text-start min-w-[13rem]'],
        ['po', 'PO Number', 'text-start'],
        ['reference', 'Reference', 'text-start'],
        ['invoice_at', 'Invoice Date', 'text-start'],
        ['gr', 'GR No.', 'text-start'],
        ['amount_submitted', 'Amount (₱)', 'text-end'],
        ['rfp', 'RFP Down Payment', 'text-start'],
        ['three_way', '3-Way Match', 'text-start'],
        ['match_flag_count', 'Invoice Check', 'text-start'],
        ['status', 'Status', 'text-start'],
        ['submitted_at', 'Waiting', 'text-start pe-6'],
    ];
@endphp

<section class="sf-card overflow-hidden" data-tour="table">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[85rem] border-collapse text-start text-sm">
            <thead class="sticky top-0 z-10 bg-gray-50/95 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 backdrop-blur dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="w-32 py-3.5 ps-6 pe-3 text-start">Actions</th>
                    @foreach ($columns as [$column, $label, $align])
                        <th class="whitespace-nowrap px-3 py-3.5 {{ $align }}">
                            @if (in_array($column, ApprovalQueue::SORTABLE, true))
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
                @forelse ($invoices as $invoice)
                    @php
                        $threeWay = $this->threeWay($invoice);
                        $statusColor = $invoice->status?->getColor() ?? 'gray';
                        $waitingDays = $invoice->submitted_at ? (int) $invoice->submitted_at->diffInDays(now()) : null;
                        $url = $this->reviewUrl($invoice);
                    @endphp
                    <tr
                        wire:key="invoice-{{ $invoice->id }}"
                        x-on:click="window.location = @js($url)"
                        class="group cursor-pointer transition hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        {{-- Actions: messages + Review --}}
                        <td class="py-3.5 ps-6 pe-3" x-on:click.stop>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    title="{{ $invoice->unread_comments_count ? 'Open '.$invoice->unread_comments_count.' unread '.str('message')->plural($invoice->unread_comments_count) : 'Open messages' }}"
                                    x-on:click="$dispatch('open-comments-thread', { type: @js($invoice->getMorphClass()), id: {{ $invoice->id }} })"
                                    @class([
                                        'relative flex size-8 items-center justify-center rounded-lg transition',
                                        'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300' => $invoice->unread_comments_count,
                                        'bg-gray-50 text-gray-400 hover:bg-gray-100 dark:bg-white/5' => ! $invoice->unread_comments_count,
                                    ])
                                >
                                    <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center" class="size-4" />
                                    @if ($invoice->unread_comments_count)
                                        <span class="absolute -end-1 -top-1 flex size-4 items-center justify-center rounded-full bg-primary-600 text-[0.625rem] font-bold leading-none text-white">{{ $invoice->unread_comments_count }}</span>
                                    @endif
                                </button>
                                <a href="{{ $url }}" class="rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:hover:bg-primary-500/20">
                                    Review
                                </a>
                            </div>
                        </td>

                        <td class="whitespace-nowrap px-3 py-3.5 font-mono font-bold">{{ $invoice->grc_number ?? '—' }}</td>

                        <td class="px-3 py-3.5">
                            <div class="font-semibold leading-tight">{{ str($invoice->vendor?->name ?? '—')->limit(40) }}</div>
                            <div class="mt-0.5 text-[0.6875rem] text-gray-500 dark:text-gray-400">
                                {{ collect([$invoice->vendor?->code, $invoice->company?->short_name])->filter()->implode(' · ') }}
                            </div>
                        </td>

                        <td class="px-3 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-300">
                            @forelse ((array) $invoice->po_numbers as $po)
                                <div>{{ $po }}</div>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </td>

                        <td class="px-3 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-300">
                            @forelse ((array) $invoice->reference_number as $reference)
                                <div>{{ $reference }}</div>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </td>

                        <td class="whitespace-nowrap px-3 py-3.5">{{ $invoice->invoice_at?->format('M j, Y') ?? '—' }}</td>

                        <td class="px-3 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-300">
                            @forelse ((array) $invoice->gr_numbers as $gr)
                                <div>{{ $gr }}</div>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </td>

                        <td class="whitespace-nowrap px-3 py-3.5 text-end font-display text-base font-bold tabular-nums">
                            {{ $invoice->amount_submitted !== null ? Peso::format((float) $invoice->amount_submitted) : '—' }}
                        </td>

                        {{-- RFPs (usually down payments) the AP Processor linked for reference --}}
                        <td class="px-3 py-3.5" x-on:click.stop>
                            @if ($invoice->linkedRfps->isNotEmpty())
                                <div class="font-semibold tabular-nums">{{ Peso::format((float) $invoice->linkedRfps->sum('total_php_amount')) }}</div>
                                @foreach ($invoice->linkedRfps->take(2) as $rfp)
                                    <a href="{{ RfpResource::getUrl('view', ['record' => $rfp]) }}" class="block text-[0.6875rem] text-primary-600 hover:underline dark:text-primary-400">{{ $rfp->rfp_number }}</a>
                                @endforeach
                                @if ($invoice->linkedRfps->count() > 2)
                                    <span class="text-[0.6875rem] text-gray-500">+{{ $invoice->linkedRfps->count() - 2 }} more</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-3 py-3.5" title="{{ $threeWay['reason'] }}">
                            <span @class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold', $badge($threeWay['color'])])>
                                <span @class(['size-1.5 rounded-full', $dot($threeWay['color'])])></span>
                                {{ $threeWay['label'] }}
                            </span>
                            @if (in_array($threeWay['color'], ['danger', 'warning'], true) && filled($threeWay['reason']))
                                <div class="mt-0.5 max-w-44 truncate ps-1 text-[0.6875rem] font-medium text-danger-600 dark:text-danger-400">{{ $threeWay['reason'] }}</div>
                            @endif
                        </td>

                        {{-- Read invoice with AI: lines that don't match the vendor's invoice --}}
                        <td class="px-3 py-3.5">
                            @if ($invoice->match_flag_count > 0)
                                <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-3.5" />
                                    {{ $invoice->match_flag_count }} to check
                                </span>
                            @elseif ($invoice->invoice_match !== null)
                                <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-semibold text-success-800 dark:bg-success-500/15 dark:text-success-300">
                                    <x-filament::icon icon="heroicon-m-check" class="size-3.5" />
                                    Checked
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-3 py-3.5">
                            @if ($invoice->status)
                                <span @class(['inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold', $badge($statusColor)])>
                                    {{ $invoice->status->getLabel() }}
                                </span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap py-3.5 ps-3 pe-6">
                            @if ($invoice->submitted_at)
                                <div class="font-medium">{{ $invoice->submitted_at->format('M j, Y') }}</div>
                                <div @class([
                                    'text-[0.6875rem]',
                                    'font-bold text-danger-600 dark:text-danger-400' => $waitingDays >= ApprovalQueue::WAITING_DAYS * 2,
                                    'font-semibold text-warning-700 dark:text-warning-400' => $waitingDays >= ApprovalQueue::WAITING_DAYS && $waitingDays < ApprovalQueue::WAITING_DAYS * 2,
                                    'text-gray-500 dark:text-gray-400' => $waitingDays < ApprovalQueue::WAITING_DAYS,
                                ])>{{ $invoice->submitted_at->diffForHumans() }}</div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-6 py-16 text-center">
                            @if ($hasFilters)
                                <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="mx-auto size-10 text-gray-300" />
                                <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">No submissions match</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Try another tab or <button type="button" wire:click="clearFilters" class="font-semibold text-primary-600 hover:underline">clear the filters</button>.
                                </p>
                            @else
                                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-success-100 dark:bg-success-500/15">
                                    <x-filament::icon icon="heroicon-s-check-badge" class="size-9 text-success-600" />
                                </div>
                                <p class="mt-3 font-display text-lg font-semibold text-gray-900 dark:text-white">{{ $tab === 'mine' ? 'You\'re all caught up' : 'Nothing here' }}</p>
                                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                                    {{ $tab === 'mine' ? 'Nothing to action. Submissions waiting on you will appear here.' : 'No submissions in this queue right now.' }}
                                </p>
                                @if ($tab === 'mine')
                                    <x-filament::button size="sm" color="gray" class="mt-4" wire:click="setTab('all')">Switch to the All tab</x-filament::button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($invoices->total())
        <div class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 bg-gray-50/40 px-6 py-4 text-xs text-gray-500 sm:flex-row dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400">
            <div class="flex items-center gap-3">
                <span class="tabular-nums">Showing <strong class="text-gray-900 dark:text-white">{{ $invoices->firstItem() }}–{{ $invoices->lastItem() }}</strong> of <strong class="text-gray-900 dark:text-white">{{ number_format($invoices->total()) }}</strong> entries</span>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <label class="flex items-center gap-1.5">
                    Per page
                    <select wire:model.live="perPage" class="rounded-lg border-0 bg-white py-1 ps-2.5 pe-7 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-white/10">
                        @foreach ([10, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }} rows</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($invoices->hasPages())
                @php
                    $current = $invoices->currentPage();
                    $last = $invoices->lastPage();
                    $pages = collect([1, $last, ...range(max(1, $current - 1), min($last, $current + 1))])->unique()->sort()->values();
                @endphp
                <nav class="flex items-center gap-1.5" aria-label="Pagination">
                    <button type="button" wire:click="previousPage" @disabled($invoices->onFirstPage()) class="rounded-lg px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-white/5">Previous</button>
                    @foreach ($pages as $page)
                        @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                            <span class="px-1">…</span>
                        @endif
                        <button
                            type="button"
                            wire:click="gotoPage({{ $page }})"
                            @class([
                                'flex size-8 items-center justify-center rounded-lg text-xs tabular-nums',
                                'bg-primary-600 font-bold text-white shadow-sm' => $page === $current,
                                'font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $page !== $current,
                            ])
                        >{{ $page }}</button>
                    @endforeach
                    <button type="button" wire:click="nextPage" @disabled(! $invoices->hasMorePages()) class="rounded-lg px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-white/5">Next</button>
                </nav>
            @endif
        </div>
    @endif
</section>
