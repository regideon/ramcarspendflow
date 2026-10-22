@php
    use App\Enums\P2pThreeWayMatchStatus;
    use App\Services\P2p\P2pThreeWayMatchService;

    $invoice = $this->getInvoice();
@endphp

<div>
    @if ($invoice !== null)
    @php
        $canEdit = $this->canEdit();
        $reviewUrl = $this->reviewUrl();
        $reviewLabel = $this->reviewLabel();

        $histories = $invoice->histories()
            ->with('actionedBy')
            ->orderBy('created_at')
            ->get();

        $escalations = $invoice->escalations()
            ->with('reason.steps')
            ->where('status', 'open')
            ->orderBy('batch_number')
            ->get();

        $matchService = app(P2pThreeWayMatchService::class);
        $money = fn ($value): string => '₱ '.number_format((float) $value, 2);
    @endphp

    <div class="rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $invoice->grc_number }}</span>
                <x-filament::badge :color="$invoice->status->getColor()">
                    {{ $invoice->status->getLabel() }}
                </x-filament::badge>
            </div>
            <div class="mt-1 truncate text-sm text-gray-600 dark:text-gray-300">{{ $invoice->vendor?->name ?? '—' }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->company?->name ?? '—' }}</div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-filament::icon-button
                icon="heroicon-o-arrow-left"
                color="gray"
                size="sm"
                label="Back to the list"
                class="lg:hidden"
                wire:click="close"
            />

            @if ($canEdit)
                <x-filament::icon-button
                    icon="heroicon-o-pencil-square"
                    color="primary"
                    size="sm"
                    label="Edit request"
                    wire:click="startEditing"
                />
            @endif

            <x-filament::icon-button
                icon="heroicon-o-x-mark"
                color="gray"
                size="sm"
                label="Close"
                wire:click="close"
            />
        </div>
    </div>

    <div class="space-y-4 p-4">
        @if ($this->isEditing)
            <form wire:submit="save" class="space-y-4">
                {{ $this->recordEdit }}

                <div class="flex items-center gap-2">
                    <x-filament::button type="submit">
                        Save
                    </x-filament::button>

                    <x-filament::button type="button" color="gray" wire:click="cancelEditing">
                        Cancel
                    </x-filament::button>
                </div>
            </form>
        @else
            <x-filament::section heading="Request">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Invoice date</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->invoice_at?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Submitted</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->submitted_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Reference</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ implode(', ', $invoice->reference_number ?? []) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Amount</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $money($invoice->total ?: $invoice->amount_submitted) }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Workflow stage">
                @include('filament.components.p2p-status-stepper', [
                    'status' => $invoice->status,
                    'histories' => $histories,
                    'escalations' => $escalations,
                ])
            </x-filament::section>

            <x-filament::section heading="Scope">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Purchase orders</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ implode(', ', $invoice->po_numbers ?? []) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Goods receipts</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ implode(', ', $invoice->gr_numbers ?? []) ?: '—' }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Lines">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="pb-2 pr-2 font-medium">Item</th>
                                <th class="pb-2 pr-2 font-medium">PO / item</th>
                                <th class="pb-2 pr-2 text-right font-medium">Qty</th>
                                <th class="pb-2 pr-2 font-medium">UoM</th>
                                <th class="pb-2 pr-2 text-right font-medium">Unit cost</th>
                                <th class="pb-2 pr-2 text-right font-medium">Amount</th>
                                <th class="pb-2 font-medium">3-way</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($invoice->lines as $line)
                                @php
                                    $match = $line->sapItem
                                        ? $matchService->match($line->sapItem, $line)
                                        : P2pThreeWayMatchStatus::ForVerification;
                                @endphp
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="py-2 pr-2">{{ $line->item_name ?: '—' }}</td>
                                    <td class="py-2 pr-2 text-gray-500 dark:text-gray-400">{{ $line->purchase_order }} / {{ $line->purchase_order_item }}</td>
                                    <td class="py-2 pr-2 text-right tabular-nums">{{ $line->quantity_billed }}</td>
                                    <td class="py-2 pr-2">{{ $line->uom ?: '—' }}</td>
                                    <td class="py-2 pr-2 text-right tabular-nums">{{ $money($line->unit_cost) }}</td>
                                    <td class="py-2 pr-2 text-right tabular-nums">{{ $money($line->amount) }}</td>
                                    <td class="py-2">
                                        <x-filament::badge :color="$match->getColor()" title="{{ $match->description() }}">
                                            {{ $match->getLabel() }}
                                        </x-filament::badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-3 text-gray-500 dark:text-gray-400">No lines on this request.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Tax summary">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Vatable sales</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $money($invoice->vatable_sales) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">VAT</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $money($invoice->vat) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Total</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $money($invoice->total) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">EWT</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $money($invoice->ewt) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Net of withholding tax</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $money($invoice->net) }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            @if (filled($invoice->invoice_number) || filled($invoice->fi_doc_no) || filled($invoice->clearing_doc_no))
                <x-filament::section heading="SAP references">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        @if (filled($invoice->invoice_number))
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">SAP invoice</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->invoice_number }}</dd>
                            </div>
                        @endif
                        @if (filled($invoice->fi_doc_no))
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">FI document</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->fi_doc_no }}</dd>
                            </div>
                        @endif
                        @if (filled($invoice->clearing_doc_no))
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Clearing document</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->clearing_doc_no }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-filament::section>
            @endif

            <x-filament::section heading="Attachments">
                @include('filament.components.open-pdf-link', ['attachments' => $invoice->attachments ?? []])
            </x-filament::section>

            <x-filament::section heading="History">
                @include('filament.components.approval-history-timeline', ['record' => $invoice])
            </x-filament::section>

            <div class="flex flex-wrap items-center gap-2">
                @if ($reviewUrl)
                    <x-filament::button tag="a" href="{{ $reviewUrl }}" size="sm" icon="heroicon-o-arrow-top-right-on-square">
                        {{ $reviewLabel }}
                    </x-filament::button>
                @endif

                @if ($canEdit)
                    <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-pencil-square" wire:click="startEditing">
                        Edit request
                    </x-filament::button>
                @endif

                <x-filament::button type="button" color="gray" size="sm" wire:click="close">
                    Close
                </x-filament::button>
            </div>
        @endif
    </div>
</div>
    @endif
</div>
