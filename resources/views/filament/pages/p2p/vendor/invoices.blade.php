{{-- Vendor Invoices, redesigned from Google Stitch. The old list stays at P2P (OLD) › P2P requests. --}}
@php
    use App\Filament\Pages\P2p\VendorInvoices;
    use App\Filament\Pages\P2p\VendorPurchaseOrders;
    use App\Support\Peso;
    use Illuminate\Support\Facades\Storage;

    $invoices = $this->invoices;
    $counts = $this->tabCounts;
    $isVendor = $this->isVendor();
    $hasFilters = filled($search) || filled($status);
@endphp

<x-filament-panels::page>
    <div class="space-y-5 pb-16">
        @include('filament.pages.p2p.vendor.steps', ['current' => 0, 'processUrl' => VendorPurchaseOrders::getUrl()])

        {{-- Tabs --}}
        <div class="overflow-x-auto pb-1" data-tour="tabs">
            <nav class="flex w-max items-center gap-1.5 rounded-2xl bg-gray-100/80 p-1 dark:bg-white/5" role="tablist">
                @foreach (VendorInvoices::TABS as $key => $label)
                    @php $active = $tab === $key; @endphp
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $active ? 'true' : 'false' }}"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'flex items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm transition',
                            'bg-primary-600 font-semibold text-white shadow-sm' => $active,
                            'font-medium text-gray-600 hover:bg-white/80 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10' => ! $active,
                        ])
                    >
                        {{ $label }}
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[0.6875rem] font-bold tabular-nums',
                            'bg-white text-primary-700' => $active,
                            'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => ! $active && $key === 'returned' && $counts[$key] > 0,
                            'bg-gray-200/80 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $active && ! ($key === 'returned' && $counts[$key] > 0),
                        ])>{{ number_format($counts[$key]) }}</span>
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
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search GRC no., invoice no. or vendor" class="w-full rounded-xl border-0 bg-gray-50 py-2 ps-9 pe-3 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10" />
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
            <span class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="size-2 animate-pulse rounded-full bg-primary-500"></span>
                <span class="tabular-nums">{{ number_format($invoices->total()) }} {{ str('result')->plural($invoices->total()) }}</span>
            </span>
        </div>

        {{-- Table --}}
        <section class="sf-card overflow-hidden" data-tour="table">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[78rem] border-collapse text-start text-sm">
                    <thead class="bg-gray-50/95 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                        <tr>
                            <th class="w-40 py-3.5 ps-6 pe-3 text-start">Actions</th>
                            @unless ($isVendor)
                                <th class="px-3 py-3.5 text-start">Vendor</th>
                            @else
                                <th class="px-3 py-3.5 text-start">Company</th>
                            @endunless
                            <th class="px-3 py-3.5 text-start">PO</th>
                            <th class="px-3 py-3.5 text-start">Reference</th>
                            <th class="px-3 py-3.5 text-start">Invoice Date</th>
                            <th class="px-3 py-3.5 text-start">GR</th>
                            @unless ($isVendor)
                                <th class="px-3 py-3.5 text-end">Amount</th>
                            @endunless
                            <th class="px-3 py-3.5 text-start">Invoice No</th>
                            <th class="px-3 py-3.5 text-start">3-Way Match</th>
                            <th class="px-3 py-3.5 text-start">Attachments</th>
                            <th class="px-3 py-3.5 text-start">Status</th>
                            <th class="py-3.5 ps-3 pe-6 text-start">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                        @forelse ($invoices as $invoice)
                            @php
                                $threeWay = $this->threeWay($invoice);
                                $url = $this->viewUrl($invoice);
                                $files = collect((array) $invoice->attachments)
                                    ->map(fn ($attachment) => is_array($attachment) ? ($attachment['path'] ?? null) : $attachment)
                                    ->filter(fn ($path) => is_string($path) && $path !== '');
                            @endphp
                            <tr
                                wire:key="invoice-{{ $invoice->id }}"
                                @if ($url) x-on:click="window.location = @js($url)" @endif
                                @class(['group transition hover:bg-gray-50 dark:hover:bg-white/5', 'cursor-pointer' => $url, 'opacity-70' => $invoice->status?->value === \App\Enums\P2pStatus::Cancelled->value])
                            >
                                <td class="py-3.5 ps-6 pe-3" x-on:click.stop>
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            title="Open messages"
                                            x-on:click="$dispatch('open-comments-thread', { type: @js($invoice->getMorphClass()), id: {{ $invoice->id }} })"
                                            @class(['relative flex size-8 items-center justify-center rounded-lg transition', 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300' => $invoice->unread_comments_count, 'bg-gray-50 text-gray-400 hover:bg-gray-100 dark:bg-white/5' => ! $invoice->unread_comments_count])
                                        >
                                            <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center" class="size-4" />
                                            @if ($invoice->unread_comments_count)
                                                <span class="absolute -end-1 -top-1 flex size-4 items-center justify-center rounded-full bg-danger-600 text-[0.625rem] font-bold leading-none text-white">{{ $invoice->unread_comments_count }}</span>
                                            @endif
                                        </button>
                                        @if ($url)
                                            <a href="{{ $url }}" class="rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300">View</a>
                                        @endif
                                        @if ($this->canResubmit($invoice))
                                            <a href="{{ $url }}" class="inline-flex items-center gap-1 rounded-lg bg-warning-100 px-2.5 py-1.5 text-xs font-bold text-warning-800 transition hover:bg-warning-200 dark:bg-warning-500/15 dark:text-warning-300">
                                                <x-filament::icon icon="heroicon-m-arrow-path" class="size-3.5" />
                                                Resubmit
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                @unless ($isVendor)
                                    <td class="px-3 py-3.5">
                                        <div class="font-semibold leading-tight">{{ str($invoice->vendor?->name ?? '—')->limit(30) }}</div>
                                        <div class="text-[0.6875rem] text-gray-500">{{ $invoice->vendor?->code }}</div>
                                    </td>
                                @else
                                    <td class="px-3 py-3.5 font-medium text-gray-700 dark:text-gray-300">{{ $invoice->company?->name ?? '—' }}</td>
                                @endunless
                                <td class="px-3 py-3.5 font-mono text-xs font-bold">
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
                                <td class="whitespace-nowrap px-3 py-3.5 text-gray-600 dark:text-gray-300">{{ $invoice->invoice_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-3 py-3.5 font-mono text-xs text-gray-500">
                                    @forelse ((array) $invoice->gr_numbers as $gr)
                                        <div>{{ $gr }}</div>
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </td>
                                @unless ($isVendor)
                                    <td class="whitespace-nowrap px-3 py-3.5 text-end font-mono font-bold tabular-nums">{{ $invoice->amount_submitted !== null ? Peso::format((float) $invoice->amount_submitted) : '—' }}</td>
                                @endunless
                                <td class="whitespace-nowrap px-3 py-3.5 font-mono text-xs">
                                    @if (filled($invoice->invoice_number))
                                        <span class="font-bold text-success-700 dark:text-success-400">{{ $invoice->invoice_number }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5" title="{{ $threeWay['reason'] }}">
                                    <x-sf.badge :color="$threeWay['color']">{{ $threeWay['label'] }}</x-sf.badge>
                                </td>
                                <td class="px-3 py-3.5" x-on:click.stop>
                                    @forelse ($files as $path)
                                        <a href="{{ Storage::disk('public')->url($path) }}" target="_blank" rel="noopener" title="{{ basename($path) }}" class="mb-1 flex max-w-40 items-center gap-1 rounded-lg bg-gray-50 px-2 py-0.5 font-mono text-[0.6875rem] text-gray-700 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300">
                                            <x-filament::icon icon="heroicon-m-document-text" class="size-3.5 shrink-0 text-danger-500" />
                                            <span class="truncate">{{ basename($path) }}</span>
                                        </a>
                                    @empty
                                        <span class="text-xs text-gray-400">No attachments</span>
                                    @endforelse
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($invoice->status)
                                        <x-sf.badge :color="$invoice->status->getColor() ?? 'gray'">{{ $invoice->status->getLabel() }}</x-sf.badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap py-3.5 ps-3 pe-6">
                                    @if ($invoice->submitted_at)
                                        <div class="font-medium">{{ $invoice->submitted_at->format('M j, Y') }}</div>
                                        <div class="text-[0.6875rem] text-gray-500">{{ $invoice->submitted_at->diffForHumans() }}</div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/10">
                                        <x-filament::icon icon="heroicon-o-inbox" class="size-6 text-gray-400" />
                                    </div>
                                    <p class="mt-3 font-display text-base font-semibold text-gray-900 dark:text-white">Nothing here yet</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        @if ($hasFilters)
                                            Try another tab or <button type="button" wire:click="clearFilters" class="font-semibold text-primary-600 hover:underline">clear the filters</button>.
                                        @else
                                            Your invoices appear here once you submit them. Start with Process.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-sf.pagination :paginator="$invoices" />
        </section>
    </div>

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
