<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pInvoiceActionLabel;
use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\WithholdingTaxCode;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptReturnService;
use App\Services\P2p\P2pInvoiceParkingService;
use App\Services\P2p\P2pTaxSummaryService;
use App\Services\P2p\P2pThreeWayMatchService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

/**
 * The AP processing screen, ported from the legacy BulkAccountsPayable page.
 *
 * One submission at a time, opened from the Approvals queue. The screen is a
 * single form (statePath `data`) laid out as
 *   [ Attachment ]  [ Header              ]
 *    ^ sticky at xl [ Items               ]
 *                   [ Tax   ] [ Summary   ]
 *                   [ Approval History    ]
 * with the role-appropriate actions in a sticky footer.
 *
 * Roles: AP Processor (tax + submit/escalate/return to vendor) and AP Supervisor
 * (the legacy "checker" — approve/park in SAP, or return to the processor).
 */
class BulkAccountsPayable extends Page implements HasActions, HasForms
{
    use HasCommentsChat;
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.p2p.bulk-accounts-payable';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'p2p/bulk-accounts-payable';

    /**
     * The submission being processed.
     */
    #[Url]
    public ?int $invoice = null;

    #[Url]
    public ?string $code = null;

    /**
     * Read-only mode: opened by a role that may review but not act.
     */
    public ?bool $isViewOnly = false;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * True when the submission has open escalations, which adds the Scope column
     * to the Items table (so a partially escalated request shows which lines are
     * out on an escalation).
     */
    public bool $showEscalationColumn = false;

    /**
     * Our normalized schema keeps submitted amounts in PHP, so the screen uses a
     * fixed symbol rather than the legacy's per-GR currency.
     */
    public string $currencyKey = 'PHP';

    public string $currencySymbol = '₱';

    /**
     * Request-scoped cache; rebuilt on the next request.
     */
    protected ?Invoice $loadedInvoice = null;

    /**
     * Whether the escalation lookup for the signed-in user has been resolved, so
     * the action labels/modals that call {@see self::escalationForUser()} during a
     * single render do not re-query the invoice's escalations each time.
     */
    protected bool $escalationForUserResolved = false;

    /**
     * The open escalation waiting on the signed-in user, if any. Cached per request.
     */
    protected ?Escalation $escalationForUser = null;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Process Accounts Payable';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::ApProcessor,
            RoleName::ApSupervisor,
            RoleName::Admin,
            // Escalation hop owners open the invoice from their queue to
            // Advance/Return the escalation (legacy opened this page for them).
            RoleName::Procurement,
            RoleName::Warehouse,
            RoleName::WarehouseRawMaterials,
            RoleName::WarehouseSpareParts,
        ]) ?? false;
    }

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function currentUserId(): ?int
    {
        return $this->currentUser()?->id;
    }

    protected function userHasRole(string|array $role): bool
    {
        $user = $this->currentUser();

        return $user?->hasRole($role) ?? false;
    }

    /**
     * The supervisor ("checker" in the legacy) only approves or returns, so the
     * Tax section is read-only for them — as it is in view-only mode.
     */
    protected function isTaxSectionReadonly(): bool
    {
        return $this->isViewOnly || $this->isSupervisor();
    }

    public function isSupervisor(): bool
    {
        return $this->userHasRole(RoleName::ApSupervisor->value);
    }

    /**
     * Whether the submission sits in the viewer's own queue, so it is safe to act
     * on. A supervisor acts on the checker queue, a processor on the processor
     * queue, and an admin on either. Everything else (other queues, escalated,
     * posted) is view-only; escalation hop owners act through the footer instead.
     */
    protected function canActOn(Invoice $invoice): bool
    {
        $awaitingProcessor = in_array($invoice->status, [
            P2pStatus::SubmittedToAp,
            P2pStatus::ApReview,
            P2pStatus::ReturnedToApProcessor,
        ], true);

        $awaitingChecker = in_array($invoice->status, [
            P2pStatus::SubmittedToChecker,
            P2pStatus::CheckerReview,
        ], true);

        return match (true) {
            $this->isSupervisor() => $awaitingChecker,
            $this->userHasRole(RoleName::Admin->value) => $awaitingProcessor || $awaitingChecker,
            default => $awaitingProcessor,
        };
    }

    /**
     * The chat bubble opens the open invoice's thread; without one it shows the inbox.
     */
    protected function commentsChatRecord(): ?Invoice
    {
        return $this->invoiceModel();
    }

    protected function invoiceModel(): ?Invoice
    {
        if (! blank($this->invoice)) {
            return $this->loadedInvoice ??= Invoice::query()
                ->with(['vendor', 'company', 'escalations.reason.steps'])
                ->find((int) $this->invoice);
        }

        if (! blank($this->code)) {
            return $this->loadedInvoice ??= Invoice::query()
                ->with(['vendor', 'company', 'escalations.reason.steps'])
                ->where('code', $this->code)
                ->first();
        }

        return null;
    }

    public function mount(): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null) {
            $this->redirect(ApprovalResource::getUrl('index'));

            return;
        }

        $this->authorizeAccess($invoice);

        // Only the viewer's own queue is actionable. The Approvals tabs surface the
        // other queues too, so anything outside the viewer's queue opens read-only.
        $this->isViewOnly = ! $this->canActOn($invoice);

        $this->data['invoice_id'] = $invoice->id;
        $this->data['vendor_name'] = $invoice->vendor?->name ?? 'N/A';
        $this->data['company_name'] = $invoice->company?->name ?? 'N/A';
        $this->data['vendor_code'] = $invoice->vendor?->code ?? 'N/A';
        $this->data['company_code'] = $invoice->company?->code ?? 'N/A';
        $this->data['grc_number'] = $invoice->grc_number;
        $this->data['invoice_at'] = $invoice->invoice_at?->toDateString();
        $this->data['attachments'] = $invoice->attachments ?? [];
        $this->data['reference_number'] = $invoice->reference_number ?? [];
        $this->data['invoice_number'] = $invoice->invoice_number;
        $this->data['remarks_ap'] = $invoice->remarks_ap;

        $this->data['tax_code'] = $invoice->tax_code;
        $this->data['withholding_tax_code'] = $invoice->withholding_tax_code;
        $this->data['withholding_tax_base'] = $invoice->withholding_tax_base;

        foreach (['vatable_sales', 'vat_exempt', 'vat_zero_rated', 'vat', 'total', 'ewt', 'net'] as $key) {
            $this->data[$key] = number_format((float) $invoice->{$key}, 2, '.', ',');
        }

        $this->data['goods_receipts'] = $this->buildGoodsReceipts($invoice);

        // The overall invoice total is what is being invoiced, not the tax base.
        $this->data['overall_invoice_total'] = number_format(
            collect($this->data['goods_receipts'])
                ->flatMap(fn (array $gr): array => $gr['goodReceiptItems'] ?? [])
                ->sum(fn (array $item): float => (float) preg_replace('/[^0-9.\-]/', '', (string) ($item['amount'] ?? 0))),
            2,
            '.',
            ',',
        );

        $this->showEscalationColumn = $invoice->escalations
            ->where('status', Escalation::STATUS_OPEN)
            ->isNotEmpty();
    }

    /**
     * The submission's lines, grouped per GR for the Items table.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildGoodsReceipts(Invoice $invoice): array
    {
        $matchService = app(P2pThreeWayMatchService::class);
        $matchByLine = [];

        $escalatedGrIds = $invoice->escalations
            ->where('status', Escalation::STATUS_OPEN)
            ->whereNotNull('p2p_good_receipt_state_id')
            ->pluck('p2p_good_receipt_state_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $lines = $invoice->lines()
            ->with('sapItem.goodReceipt')
            ->get();

        // Escalations point at p2p_good_receipt_states — not at the SAP receipt behind
        // it — so map each GR on the invoice to its state row by GR number (falling
        // back to the SAP receipt id) before the rows are built.
        $states = GoodReceiptState::query()
            ->whereIn('good_receipt_number', collect($invoice->gr_numbers ?? [])->filter()->values()->all())
            ->get(['id', 'good_receipt_number', 'p2p_sap_good_receipt_id']);

        $stateIdsByGrNumber = $states->pluck('id', 'good_receipt_number');
        $stateIdsBySapReceiptId = $states->pluck('id', 'p2p_sap_good_receipt_id');

        // One pass for every mirror GR item on the invoice's POs, so the 3-way
        // breakdown is derived from in-memory data instead of a query per line
        // (which the query detector reports as an N+1).
        $matchItems = SapGoodReceiptItem::query()
            ->whereIn('purchase_order', $lines->pluck('purchase_order')->filter()->unique()->values()->all())
            ->whereNull('merged_at')
            ->with('goodReceipt')
            ->get();

        $reversedGoodReceiptIds = $matchService->reversedGoodReceiptIds($matchItems);
        $billedByItem = $matchService->billedQuantitiesFor($matchItems->pluck('id'));
        $itemsByPoLine = $matchItems->groupBy(
            fn (SapGoodReceiptItem $item): string => $item->purchase_order.'|'.$item->purchase_order_item,
        );

        return $lines
            ->groupBy(fn (InvoiceLine $line): string => (string) ($line->sapItem?->goodReceipt?->good_receipt_number ?? $line->purchase_order))
            ->map(function (Collection $grLines, string $grNumber) use ($matchService, $itemsByPoLine, $reversedGoodReceiptIds, $billedByItem, &$matchByLine, $escalatedGrIds, $stateIdsByGrNumber, $stateIdsBySapReceiptId): array {
                $first = $grLines->first();
                $stateId = (int) ($stateIdsByGrNumber[$grNumber]
                    ?? $stateIdsBySapReceiptId[(int) ($first?->sapItem?->p2p_sap_good_receipt_id ?? 0)]
                    ?? 0);
                $isEscalated = in_array($stateId, $escalatedGrIds, true);

                $rows = $grLines->map(function (InvoiceLine $line) use ($matchService, $itemsByPoLine, $reversedGoodReceiptIds, $billedByItem, &$matchByLine, $isEscalated): array {
                    $lineKey = $line->purchase_order.'|'.$line->purchase_order_item;

                    $match = $matchByLine[$lineKey] ??= $matchService->breakdownForItems(
                        $itemsByPoLine->get($lineKey, collect()),
                        $reversedGoodReceiptIds,
                        $billedByItem,
                    );

                    $unitCost = (float) $line->unit_cost;
                    $quantity = (int) $line->quantity_billed;

                    return [
                        'p2p_sap_good_receipt_item_id' => $line->p2p_sap_good_receipt_item_id,
                        'purchase_order' => $line->purchase_order,
                        'purchase_order_item' => $line->purchase_order_item,
                        'material_doc_item' => $line->sapItem?->material_doc_item,
                        'escalation_status' => $isEscalated ? 'Escalated' : 'Not escalated',
                        'item_name' => $line->item_name,
                        'item_description' => $line->item_description ?: $line->sapItem?->item_description ?: $line->item_name,
                        'quantity_po' => (int) $line->quantity_po,
                        'uom' => $line->uom,
                        'quantity_served' => (int) ($line->sapItem?->quantity_served ?? $line->quantity_po),
                        'unit_cost' => $this->currencySymbol.' '.number_format($unitCost, 2, '.', ','),
                        'amount' => $this->currencySymbol.' '.number_format($quantity * $unitCost, 2, '.', ','),
                        'escalate' => false,
                        'match_detail' => $match,
                    ];
                })->values()->all();

                return [
                    'p2p_good_receipt_state_id' => $stateId ?: null,
                    'purchase_order' => $first?->purchase_order,
                    'good_receipt_number' => $grNumber,
                    'is_escalated' => $isEscalated,
                    'goodReceiptItems' => $rows,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What the AP Processor should check from Read invoice with AI: flagged lines, the vendor's
     * remarks, warnings about the invoice, invoice lines not billed and extra charges.
     */
    protected function invoiceMatchSummary(): HtmlString
    {
        $match = $this->invoiceModel()?->invoice_match ?? [];
        $list = fn (iterable $items): string => '<ul style="margin:.25rem 0 .5rem;padding-left:1.125rem;list-style:disc;">'
            .collect($items)->map(fn (string $item): string => '<li>'.e($item).'</li>')->implode('').'</ul>';

        $sections = array_filter([
            filled($match['flags'] ?? []) ? '<strong>Lines</strong>'.$list(collect($match['flags'])->map(fn (array $flag): string => sprintf(
                'GR %s · %s (PO %s/%s): %s',
                $flag['gr_number'] ?? '—',
                $flag['item_name'] ?? '—',
                $flag['purchase_order'] ?? '—',
                $flag['purchase_order_item'] ?? '—',
                implode(' ', $flag['reasons'] ?? []),
            ))) : null,
            filled($match['vendor_remarks'] ?? null) ? '<strong>Vendor\'s remarks</strong>'.$list([$match['vendor_remarks']]) : null,
            filled($match['warnings'] ?? []) ? '<strong>Invoice</strong>'.$list($match['warnings']) : null,
            filled($match['unmatched_lines'] ?? []) ? '<strong>On the invoice but not billed here</strong>'.$list(collect($match['unmatched_lines'])->map(fn (array $line): string => sprintf(
                '%s: %s · qty %s @ %s',
                $line['invoice_number'] ?? '—',
                $line['description'] ?? '—',
                $line['quantity'] ?? '—',
                is_numeric($line['unit_price'] ?? null) ? number_format((float) $line['unit_price'], 2) : '—',
            ))) : null,
            filled($match['charges'] ?? []) ? '<strong>Charges not on any PO</strong>'.$list(collect($match['charges'])->map(fn (array $charge): string => sprintf(
                '%s: %s · %s',
                $charge['invoice_number'] ?? '—',
                $charge['description'] ?? 'Charge',
                number_format((float) $charge['amount'], 2),
            ))) : null,
        ]);

        return new HtmlString(implode('', $sections));
    }

    public function form(Schema $schema): Schema
    {
        $showEscalationColumn = $this->showEscalationColumn;

        return $schema
            ->schema([
                // The vendor submitted lines that don't match their invoice (Read invoice with AI)
                Callout::make(fn (): string => ($count = $this->invoiceModel()?->match_flag_count ?? 0) > 0
                    ? "{$count} ".str('line')->plural($count).' to check against the vendor\'s invoice'
                    : 'Check the vendor\'s invoice')
                    ->description(fn (): HtmlString => $this->invoiceMatchSummary())
                    ->warning()
                    ->visible(fn (): bool => $this->invoiceMatchSummary()->toHtml() !== '')
                    ->columnSpanFull(),

                Group::make()
                    ->columnSpan(['default' => 12])
                    ->schema([
                        Section::make('Accounts Payable')
                            ->columns(['default' => 1, 'md' => 12])
                            ->description('Review and validate the submitted Accounts Payable before forwarding them for approval.')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->iconColor('primary')
                            ->schema($this->getAccountsPayableFields()),
                    ]),

                Group::make()
                    ->columnSpan(['default' => 12])
                    ->columns(['default' => 1, 'lg' => 12])
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 12])
                            ->schema([
                                Section::make('Items')
                                    ->description('The items selected for purchase')
                                    ->icon(Heroicon::OutlinedShoppingBag)
                                    ->iconColor('primary')
                                    ->schema([
                                        Repeater::make('goods_receipts')
                                            ->hiddenLabel()
                                            ->default($this->data['goods_receipts'] ?? [])
                                            ->schema([
                                                Hidden::make('p2p_good_receipt_state_id'),
                                                Hidden::make('purchase_order'),

                                                Grid::make()
                                                    ->columns(2)
                                                    ->schema([
                                                        ViewField::make('good_receipt_number')
                                                            ->label('Goods Receipt Number')
                                                            ->view('filament.components.label-value')
                                                            ->dehydrated(),

                                                        ViewField::make('purchase_order')
                                                            ->label('Purchase Order Number')
                                                            ->view('filament.components.label-value')
                                                            ->dehydrated(),
                                                    ]),

                                                Repeater::make('goodReceiptItems')
                                                    ->compact()
                                                    ->hiddenLabel()
                                                    ->table([
                                                        ...($showEscalationColumn ? [TableColumn::make('Scope')] : []),
                                                        TableColumn::make('Description'),
                                                        TableColumn::make('Item No.'),
                                                        TableColumn::make('Uom'),
                                                        TableColumn::make('PO Qty'),
                                                        TableColumn::make('Invoice Qty'),
                                                        TableColumn::make('UC'),
                                                        TableColumn::make('Amount'),
                                                        TableColumn::make('3-Way'),
                                                    ])
                                                    ->schema([
                                                        ...($showEscalationColumn ? [
                                                            TextEntry::make('escalation_status')
                                                                ->hiddenLabel()
                                                                ->badge()
                                                                ->color(fn ($state): string => $state === 'Escalated' ? 'warning' : 'gray'),
                                                        ] : []),

                                                        TextEntry::make('item_description')
                                                            ->hiddenLabel()
                                                            ->wrap(),

                                                        TextEntry::make('purchase_order_item')
                                                            ->hiddenLabel(),

                                                        TextEntry::make('uom')
                                                            ->hiddenLabel(),

                                                        TextEntry::make('quantity_po')
                                                            ->hiddenLabel(),

                                                        TextEntry::make('quantity_served')
                                                            ->hiddenLabel(),

                                                        TextEntry::make('unit_cost')
                                                            ->hiddenLabel(),

                                                        TextEntry::make('amount')
                                                            ->hiddenLabel(),

                                                        ViewField::make('match_detail')
                                                            ->hiddenLabel()
                                                            ->view('filament.components.three-way-match-badge')
                                                            ->viewData(fn ($state): array => is_array($state) ? $state : []),
                                                    ])
                                                    ->addable(false)
                                                    ->deletable(false)
                                                    ->reorderable(false),

                                                TextEntry::make('totals_row')
                                                    ->hiddenLabel()
                                                    ->state(function ($get): HtmlString {
                                                        $items = $get('goodReceiptItems') ?? [];

                                                        $totalPoQty = collect($items)->sum(fn ($i): float => (float) ($i['quantity_po'] ?? 0));
                                                        $totalServed = collect($items)->sum(fn ($i): float => (float) ($i['quantity_served'] ?? 0));
                                                        $totalAmount = collect($items)->sum(
                                                            fn ($i): float => (float) preg_replace('/[^0-9.\-]/', '', (string) ($i['amount'] ?? 0)),
                                                        );

                                                        $poQtyFmt = number_format($totalPoQty, 0);
                                                        $servedFmt = number_format($totalServed, 0);
                                                        $amountFmt = $this->currencySymbol.' '.number_format($totalAmount, 2, '.', ',');

                                                        return new HtmlString(<<<HTML
            <div style="margin-top:8px; display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:16px; border-top:2px solid #e5e7eb; padding:12px 16px; font-weight:600;">
                <span style="color:#6b7280;">Total PO Qty: <span style="color:#111827;">{$poQtyFmt}</span></span>
                <span style="color:#6b7280;">Total Invoice Qty: <span style="color:#111827;">{$servedFmt}</span></span>
                <span style="color:#6b7280;">Total Amount: <span style="color:#111827;">{$amountFmt}</span></span>
            </div>
        HTML);
                                                    })
                                                    ->columnSpanFull(),
                                            ])
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        ...$this->getProcessorComponents(),

                        Group::make()
                            ->columnSpan(['default' => 12])
                            ->schema([
                                Section::make('Approval History')
                                    ->description('All actions and remarks related to this goods receipt')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->collapsible()
                                    ->schema([
                                        ViewField::make('approval_history_timeline')
                                            ->hiddenLabel()
                                            ->view('filament.components.approval-history-timeline')
                                            ->viewData(fn (): array => ['record' => $this->invoiceModel()])
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, mixed>
     */
    protected function getAccountsPayableFields(): array
    {
        return [
            TextInput::make('vendor_name')
                ->label('Vendor')
                ->readOnly()
                ->dehydrated(false)
                ->columnSpan(['default' => 12, 'md' => 4]),

            TextInput::make('company_name')
                ->label('Company')
                ->readOnly()
                ->dehydrated(false)
                ->columnSpan(['default' => 12, 'md' => 4]),

            ViewField::make('attachments')
                ->label('Attached PDF')
                ->view('filament.components.open-pdf-link')
                ->viewData(fn (): array => ['attachments' => $this->data['attachments'] ?? []])
                ->columnSpan(['default' => 12, 'md' => 4]),

            TextInput::make('invoice_at')
                ->label('Invoice Date')
                ->readOnly()
                ->dehydrated(false)
                ->columnSpan(['default' => 12, 'md' => 4]),

            TextInput::make('reference_number')
                ->label('Reference No.')
                ->readOnly()
                ->dehydrated(false)
                ->columnSpan(['default' => 12, 'md' => 4]),

            TextInput::make('overall_invoice_total')
                ->label('Overall Invoice Total')
                ->readOnly()
                ->prefix($this->currencySymbol)
                ->dehydrated(false)
                ->columnSpan(['default' => 12, 'md' => 4]),

            TextInput::make('invoice_number')
                ->label('Invoice No.')
                ->readOnly()
                ->dehydrated(false)
                ->placeholder('SAP-populated on posting')
                ->columnSpan(['default' => 12, 'md' => 4]),
        ];
    }

    /**
     * Tax + Summary. Read-only for the supervisor and in view-only mode.
     *
     * @return array<int, mixed>
     */
    protected function getProcessorComponents(): array
    {
        return [
            Group::make()
                ->columnSpan(['default' => 12, 'lg' => 6])
                ->schema([
                    Section::make('Tax')
                        ->icon(Heroicon::OutlinedReceiptPercent)
                        ->iconColor('primary')
                        ->columns(['default' => 1, 'md' => 12])
                        ->schema([
                            Select::make('withholding_tax_code')
                                ->label('Withholding Tax Code')
                                ->options(fn (): array => self::withholdingTaxOptions())
                                ->searchable()
                                ->preload()
                                ->disabled($this->isTaxSectionReadonly())
                                // Disabled fields are not dehydrated by default, so
                                // without this a supervisor's action would post null
                                // and wipe the processor's tax values.
                                ->dehydrated()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set))
                                ->columnSpan(['default' => 12, 'md' => 6]),

                            TextInput::make('withholding_tax_base')
                                ->label('Withholding Tax Base')
                                ->numeric()
                                ->prefix($this->currencySymbol)
                                ->disabled($this->isTaxSectionReadonly())
                                ->dehydrated()
                                ->live(debounce: 800)
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set))
                                ->columnSpan(['default' => 12, 'md' => 6]),

                            Select::make('tax_code')
                                ->label('Tax Code')
                                ->options(fn (): array => self::taxCodeOptions())
                                ->searchable()
                                ->disabled($this->isTaxSectionReadonly())
                                ->dehydrated()
                                ->markAsRequired(! $this->isTaxSectionReadonly())
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set))
                                ->columnSpan(['default' => 12]),

                            Textarea::make('remarks_ap')
                                ->label('AP Notes')
                                ->disabled($this->isTaxSectionReadonly())
                                ->dehydrated()
                                ->columnSpan(['default' => 12]),
                        ]),
                ]),

            Group::make()
                ->columnSpan(['default' => 12, 'lg' => 6])
                ->schema([
                    Section::make('Summary')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->iconColor('primary')
                        ->schema($this->getSummaryFields()),
                ]),
        ];
    }

    /**
     * Recompute the VAT/EWT summary from the current tax selection. The tax
     * service owns the maths; this only feeds the read-only inputs.
     */
    protected function recalculateTaxSummary(Set $set): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null) {
            return;
        }

        $taxCode = $this->data['tax_code'] !== null
            ? TaxCode::query()->where('code', $this->data['tax_code'])->first()
            : null;

        $withholding = $this->data['withholding_tax_code'] !== null
            ? WithholdingTaxCode::query()->where('code', $this->data['withholding_tax_code'])->first()
            : null;

        $amount = collect($this->data['goods_receipts'] ?? [])
            ->flatMap(fn (array $gr): array => $gr['goodReceiptItems'] ?? [])
            ->sum(fn (array $item): float => (float) preg_replace('/[^0-9.\-]/', '', (string) ($item['amount'] ?? 0)));

        $sales = number_format($amount > 0 ? $amount : (float) $invoice->amount_submitted, 2, '.', '');

        $withholdingBase = $this->data['withholding_tax_base'] !== null
            ? (float) preg_replace('/[^0-9.\-]/', '', (string) $this->data['withholding_tax_base'])
            : (float) $sales;

        $summary = app(P2pTaxSummaryService::class)->calculateSummary(
            $sales,
            $taxCode,
            (string) $withholdingBase,
            $withholding,
        );

        foreach (['vatable_sales', 'vat_exempt', 'vat_zero_rated', 'vat', 'total', 'ewt', 'net'] as $key) {
            $value = number_format((float) ($summary[$key] ?? 0), 2, '.', '');

            $set($key, $value);
            $this->data[$key] = $value;
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function getSummaryFields(): array
    {
        $columnSpan = ['default' => 12, 'md' => 6];

        return [
            $this->summaryEntry('vatable_sales', 'Vatable Sales', $columnSpan),
            $this->summaryEntry('vat_exempt', 'VAT Exempt', $columnSpan),
            $this->summaryEntry('vat_zero_rated', 'VAT Zero Rated', $columnSpan),
            $this->summaryEntry('vat', 'VAT', $columnSpan),
            $this->summaryEntry('total', 'Total', $columnSpan),
            $this->summaryEntry('ewt', 'EWT', $columnSpan),
            $this->summaryEntry('net', 'NET', ['default' => 12]),
        ];
    }

    /**
     * @param  array<string, int>  $columnSpan
     */
    protected function summaryEntry(string $name, string $label, array $columnSpan): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix($this->currencySymbol)
            ->disabled()
            ->dehydrateStateUsing(fn ($state): float => (float) str_replace(',', '', (string) ($state ?? '0')))
            ->dehydrated()
            ->columnSpan($columnSpan);
    }

    /**
     * Persist the tax section onto the invoice and recompute the summary.
     */
    protected function persistTax(Invoice $invoice): void
    {
        $invoice->forceFill([
            'withholding_tax_code' => $this->data['withholding_tax_code'] ?? null,
            'withholding_tax_base' => isset($this->data['withholding_tax_base'])
                ? (float) preg_replace('/[^0-9.\-]/', '', (string) $this->data['withholding_tax_base'])
                : null,
            'tax_code' => $this->data['tax_code'] ?? null,
            'remarks_ap' => $this->data['remarks_ap'] ?? null,
        ])->save();

        app(P2pTaxSummaryService::class)->recalculate($invoice);
    }

    /**
     * @return array<string, string>
     */
    protected static function taxCodeOptions(): array
    {
        return TaxCode::query()
            ->pluck('description', 'code')
            ->map(fn (string $description, string $code): string => $code.' - '.$description)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function withholdingTaxOptions(): array
    {
        return WithholdingTaxCode::query()
            ->pluck('description', 'code')
            ->map(fn (string $description, string $code): string => $code.' - '.$description)
            ->all();
    }

    // ---------------------------------------------------------------------
    // Footer actions
    // ---------------------------------------------------------------------

    /**
     * @return array<int, Action>
     */
    public function getFooterActions(): array
    {
        // An open escalation that is waiting on this user owns the footer: the
        // static actions (Submit, Return to Vendor, ...) would be wrong, so the
        // legacy replaces them with Advance / Return / Cancel.
        $escalation = $this->escalationForUser();

        if ($escalation !== null) {
            return [
                $this->advanceEscalationAction(),
                $this->returnEscalationAction(),
                $this->backAction(),
            ];
        }

        $invoice = $this->invoiceModel();

        if ($invoice === null) {
            return [$this->backAction()];
        }

        // A failed SOAP park leaves the invoice at 21 with the error recorded; the
        // supervisor can retry without returning it to the processor.
        if ($invoice->status === P2pStatus::PendingPost && $this->isSupervisor()) {
            return [$this->retryParkAction(), $this->backAction()];
        }

        if ($this->isViewOnly) {
            return [$this->backAction()];
        }

        // Supervisor ("checker"): approve (park in SAP) or return to the processor.
        if ($this->isSupervisor()) {
            return [
                $this->approveAction(),
                $this->returnToApProcessorAction(),
                $this->backAction(),
            ];
        }

        // Processor: submit to the supervisor, escalate, or return to the vendor.
        return [
            $this->submitAction(),
            $this->escalateAction(),
            $this->returnToVendorAction(),
            $this->backAction(),
        ];
    }

    /**
     * The invoice's open escalation whose current hop is owned by the signed-in
     * user, if any. Mirrors the legacy `escalationForUser()`: it is the one that
     * turns the footer into Advance / Return.
     */
    protected function escalationForUser(): ?Escalation
    {
        if ($this->escalationForUserResolved) {
            return $this->escalationForUser;
        }

        $this->escalationForUserResolved = true;

        $user = $this->currentUser();
        $invoice = $this->invoiceModel();

        if ($user === null || $invoice === null) {
            return null;
        }

        return $this->escalationForUser = $invoice->escalations
            ->where('status', Escalation::STATUS_OPEN)
            ->first(fn (Escalation $escalation): bool => $this->userHandlesEscalation($user, $escalation));
    }

    /**
     * Whether the user owns the escalation's current hop. Hop roles are the
     * lowercase legacy tokens, so map them to the app's TitleCase Spatie roles.
     * The umbrella `warehouse` hop is handled by any warehouse role.
     */
    protected function userHandlesEscalation(User $user, Escalation $escalation): bool
    {
        $hopRole = $escalation->current_role;

        if ($hopRole === 'warehouse') {
            return $user->hasAnyRole([
                RoleName::Warehouse,
                RoleName::WarehouseRawMaterials,
                RoleName::WarehouseSpareParts,
            ]);
        }

        $appRole = Escalation::appRoleFor($hopRole);

        return $appRole !== null && $user->hasRole($appRole);
    }

    protected function advanceEscalationAction(): Action
    {
        return Action::make('advanceEscalation')
            ->label(fn (): string => $this->escalationForUser()?->currentActionLabel() ?? 'Advance')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('warning')
            ->modalHeading('Advance escalation')
            ->modalDescription(fn (): ?string => $this->escalationForUser()?->stepLabel())
            ->modalSubmitActionLabel('Advance')
            ->schema([
                Textarea::make('remarks')->label('Remarks')->required(),
            ])
            ->action(fn (array $data) => $this->advanceOpenEscalation($data));
    }

    protected function returnEscalationAction(): Action
    {
        return Action::make('returnEscalation')
            ->label(fn (): string => 'Return to '.($this->escalationForUser()?->previousRoleLabel() ?? 'previous step'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->modalHeading('Return escalation')
            ->modalDescription(fn (): ?string => $this->escalationForUser()?->stepLabel())
            ->modalSubmitActionLabel('Return')
            ->schema([
                Textarea::make('remarks')->label('Remarks')->required(),
            ])
            ->action(fn (array $data) => $this->returnOpenEscalation($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function advanceOpenEscalation(array $data): void
    {
        $escalation = $this->escalationForUser();

        if ($escalation === null) {
            return;
        }

        app(P2pEscalationRoutingService::class)->advance($escalation, $this->currentUser(), (string) ($data['remarks'] ?? ''));

        Notification::make()
            ->title('Escalation advanced')
            ->body('The escalation has been forwarded to the next step.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function returnOpenEscalation(array $data): void
    {
        $escalation = $this->escalationForUser();

        if ($escalation === null) {
            return;
        }

        app(P2pEscalationRoutingService::class)->returnToPreviousHop($escalation, $this->currentUser(), (string) ($data['remarks'] ?? ''));

        Notification::make()
            ->title('Escalation returned')
            ->body('The escalation has been sent back to the previous step.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    protected function submitAction(): Action
    {
        return Action::make('submit')
            ->label($this->invoiceModel()?->status === P2pStatus::ReturnedToApProcessor
                ? P2pInvoiceActionLabel::Resubmit->value
                : P2pInvoiceActionLabel::Submit->value)
            ->color('primary')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->action(fn () => $this->submit());
    }

    protected function approveAction(): Action
    {
        return Action::make('approve')
            ->label(P2pInvoiceActionLabel::Approve->value)
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalHeading('Approve and park this invoice in SAP?')
            ->schema([
                Textarea::make('remarks_checker')->label('Remarks')->required(),
            ])
            ->action(fn (array $data) => $this->approve($data));
    }

    protected function retryParkAction(): Action
    {
        return Action::make('retryPark')
            ->label('Retry SAP parking')
            ->color('warning')
            ->icon(Heroicon::OutlinedArrowPath)
            ->schema([
                Textarea::make('remarks_checker')
                    ->label('Remarks')
                    ->default('Retry SAP parking.')
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $parked = app(P2pInvoiceParkingService::class)->retry(
                        $this->invoiceModel(),
                        $this->currentUser(),
                        (string) ($data['remarks_checker'] ?? 'Retry SAP parking.'),
                    );

                    Notification::make()
                        ->title('Invoice parked')
                        ->body('SAP document '.($parked->invoice_number ?? '—').'.')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('SAP parking failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }

                $this->redirectBack();
            });
    }

    protected function returnToApProcessorAction(): Action
    {
        return Action::make('returnToApProcessor')
            ->label(P2pInvoiceActionLabel::ReturnToApProcessor->value)
            ->color('danger')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->schema([
                Textarea::make('remarks_checker')->label('Remarks')->required(),
            ])
            ->action(fn (array $data) => $this->returnToApProcessor($data));
    }

    protected function returnToVendorAction(): Action
    {
        return Action::make('returnToVendor')
            ->label(P2pInvoiceActionLabel::ReturnToVendor->value)
            ->color('danger')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->schema([
                Textarea::make('remarks_ap')->label('Remarks')->required(),
            ])
            ->action(fn (array $data) => $this->returnToVendor($data));
    }

    protected function escalateAction(): Action
    {
        return Action::make('escalate')
            ->label(P2pInvoiceActionLabel::Escalate->value)
            ->color('warning')
            ->icon(Heroicon::OutlinedArrowTrendingUp)
            ->schema([
                Select::make('escalationReason')
                    ->label('Reason')
                    ->options(fn (): array => EscalationReason::query()
                        ->active()
                        ->forRole('processor')
                        ->pluck('name', 'code')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('escalationRemarks')
                    ->label('Remarks')
                    ->required(),
            ])
            ->action(fn (array $data) => $this->escalate($data));
    }

    protected function backAction(): Action
    {
        return Action::make('back')
            ->label(P2pInvoiceActionLabel::Cancel->value)
            ->color('gray')
            ->action(fn () => $this->redirectBack());
    }

    // ---------------------------------------------------------------------
    // Handlers
    // ---------------------------------------------------------------------

    public function submit(): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || $this->isViewOnly || $this->isSupervisor()) {
            return;
        }

        if (blank($this->data['tax_code'] ?? null)) {
            Notification::make()
                ->title('Submission failed')
                ->body('Please select a Tax Code before submitting.')
                ->danger()
                ->send();

            return;
        }

        $this->persistTax($invoice);

        $from = $invoice->status;

        $invoice->forceFill([
            'status' => P2pStatus::SubmittedToChecker,
            'processed' => true,
        ])->save();

        $this->writeHistory($invoice, $from, P2pStatus::SubmittedToChecker, $this->data['remarks_ap'] ?? null, false);

        Notification::make()
            ->title('Submitted successfully')
            ->body('The submission has been sent to the checker for verification.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(array $data): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || ! $this->isSupervisor()) {
            return;
        }

        $remarks = (string) ($data['remarks_checker'] ?? '');

        if (trim($remarks) === '') {
            $this->addError('checkerRemarks', 'Remarks are required.');

            return;
        }

        try {
            $parked = app(P2pInvoiceParkingService::class)->park($invoice, $this->currentUser(), $remarks);

            $message = $parked->status === P2pStatus::InvoiceParked
                ? 'Invoice parked in SAP'.($parked->invoice_number ? " (doc {$parked->invoice_number})" : '').'.'
                : 'The invoice is queued for parking in SAP (Pending Post).';

            Notification::make()
                ->title('Approved')
                ->body($message)
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('SAP parking failed')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function returnToApProcessor(array $data): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || ! $this->isSupervisor()) {
            return;
        }

        $remarks = (string) ($data['remarks_checker'] ?? '');

        if (trim($remarks) === '') {
            $this->addError('checkerRemarks', 'Remarks are required.');

            return;
        }

        app(P2pGoodReceiptReturnService::class)->returnToApProcessor($invoice, $this->currentUser(), $remarks);

        Notification::make()
            ->title('Returned to AP Processor')
            ->body('The submission has been sent back for correction.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function returnToVendor(array $data): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || $this->isViewOnly || $this->isSupervisor()) {
            return;
        }

        $remarks = (string) ($data['remarks_ap'] ?? '');

        if (trim($remarks) === '') {
            $this->addError('returnRemarks', 'Remarks are required.');

            return;
        }

        $this->persistTax($invoice);

        app(P2pGoodReceiptReturnService::class)->returnToVendor($invoice, $this->currentUser(), $remarks);

        Notification::make()
            ->title('Returned to Vendor')
            ->body('The submission has been returned for correction.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function escalate(array $data): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || $this->isViewOnly || $this->isSupervisor()) {
            return;
        }

        $reason = (string) ($data['escalationReason'] ?? '');
        $remarks = (string) ($data['escalationRemarks'] ?? '');

        if ($reason === '' || trim($remarks) === '') {
            Notification::make()
                ->title('Escalation failed')
                ->body('A reason and remarks are required.')
                ->danger()
                ->send();

            return;
        }

        // The per-line Report tick has been removed, so the footer Escalate
        // covers the whole request. Prefer ticked lines first so a line-level UI
        // can be reintroduced without changing this handler.
        $stateIds = $this->tickedGoodReceiptStateIds();

        if ($stateIds === []) {
            $stateIds = $this->allGoodReceiptStateIds();
        }

        if ($stateIds === []) {
            Notification::make()
                ->title('Nothing to escalate')
                ->body('This request has no goods receipt lines to escalate.')
                ->warning()
                ->send();

            return;
        }

        $this->persistTax($invoice);

        try {
            app(P2pEscalationRoutingService::class)->start(
                $invoice,
                $reason,
                $stateIds,
                $this->currentUser(),
                $remarks,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Escalation failed')
                ->body($exception->validator?->errors()->first() ?? $exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Escalated')
            ->body('The record has been escalated for review.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * The GR state ids for the lines the processor ticked.
     *
     * @return array<int, int>
     */
    protected function tickedGoodReceiptStateIds(): array
    {
        return collect($this->data['goods_receipts'] ?? [])
            ->filter(fn (array $gr): bool => collect($gr['goodReceiptItems'] ?? [])
                ->contains(fn (array $item): bool => ! empty($item['escalate'])))
            ->pluck('p2p_good_receipt_state_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every GR state on the request, used when no line was ticked (the per-line
     * Report column was removed and Escalate now covers the whole request).
     *
     * @return array<int, int>
     */
    protected function allGoodReceiptStateIds(): array
    {
        return collect($this->data['goods_receipts'] ?? [])
            ->pluck('p2p_good_receipt_state_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function writeHistory(Invoice $invoice, ?P2pStatus $from, P2pStatus $to, ?string $remarks, bool $visibleToVendor): void
    {
        $invoice->histories()->create([
            'actioned_by' => $this->currentUserId(),
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'is_visible_to_vendor' => $visibleToVendor,
            'is_visible_to_admin' => true,
        ]);
    }

    protected function authorizeAccess(Invoice $invoice): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            abort(403);
        }

        // A vendor never reaches the AP screen.
        if ($user->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            abort(403, 'You are not authorized to process this record.');
        }
    }

    protected function redirectBack(): void
    {
        $this->redirect(ApprovalResource::getUrl('index'));
    }
}
