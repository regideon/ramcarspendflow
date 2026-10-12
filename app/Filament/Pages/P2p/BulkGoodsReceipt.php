<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptReturnService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use App\Services\P2p\P2pThreeWayMatchService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The vendor's bulk processing screen: one submission across the selected GRs (which
 * may span several POs). Mirrors legacy BulkGoodsReceipt — items are grouped per GR
 * ("Items for GR #…"), each row is a GR item, and the vendor submits to AP or
 * escalates.
 *
 * Two entry points:
 * - create:   `?ids[]=<p2p_good_receipt_states.id>` (from the Goods Receipts list)
 * - returned: `?invoice=<p2p_invoices.id>` (a returned request the vendor edits and
 *   re-submits, or cancels)
 */
class BulkGoodsReceipt extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'p2p/bulk-goods-receipt';

    protected static ?string $title = 'Process Goods Receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    /**
     * GR states to process (create mode).
     *
     * @var array<int, int>
     */
    #[Url]
    public array $ids = [];

    /**
     * Invoice to view / re-submit (returned mode).
     */
    #[Url]
    public ?int $invoice = null;

    public ?bool $isViewOnly = false;

    public ?bool $isReturned = false;

    public ?string $invoiceAt = null;

    public ?string $vendor_name = null;

    public ?string $company_name = null;

    public ?string $grc_number = null;

    /**
     * @var array<int, string>
     */
    public array $reference_number = [];

    /**
     * @var array<int, string>
     */
    public array $attachments = [];

    /**
     * One entry per GR, each with its own item rows.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $goods_receipts = [];

    public ?string $escalationReason = null;

    public string $escalationRemarks = '';

    /**
     * Request-scoped invoice cache; rebuilt on the next request.
     */
    protected ?Invoice $loadedInvoice = null;

    /**
     * Disabled "Overall Invoice Total" input, recomputed whenever the rows change.
     */
    public string $overall_invoice_total = '0.00';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin]) ?? false;
    }

    /**
     * Scopes the page's custom CSS (see public/css/app/custom-stylesheet.css).
     *
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['p2p-bulk-goods-receipt'];
    }

    public function mount(): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice !== null) {
            $this->authorizeInvoiceOwnership($invoice);

            $this->isReturned = $invoice->status === P2pStatus::ReturnedToVendor;
            $this->isViewOnly = ! $this->isReturned;

            $this->grc_number = $invoice->grc_number;
            $this->reference_number = array_values($invoice->reference_number ?? []);
            $this->attachments = array_values($invoice->attachments ?? []);
            $this->invoiceAt = optional($invoice->invoice_at)->toDateString() ?? now()->toDateString();
        } else {
            $this->invoiceAt = now()->toDateString();
        }

        $first = $this->receipts->first();
        $this->vendor_name = $first?->vendor?->name ?? 'N/A';
        $this->company_name = $first?->company?->name ?? 'N/A';

        $this->goods_receipts = $this->buildGoodsReceipts($invoice);
        $this->recalculateTotal();
    }

    public function content(Schema $schema): Schema
    {
        $hidePricing = $this->hidePricing();

        return $schema->components([
            View::make('filament.components.p2p-status-stepper')
                ->viewData(fn(): array => $this->stepperData())
                ->columnSpanFull(),

            Section::make('Goods Receipt Information')
                ->description('View vendor and company details for this receipt')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->schema([
                    Grid::make()
                        ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->schema([
                            FileUpload::make('attachments')
                                ->label('Upload Attachments (PDF)')
                                ->disk('public')
                                ->directory('attachments-pdf')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(5024)
                                ->multiple()
                                ->previewable()
                                ->openable()
                                ->downloadable()
                                ->visibility('public')
                                ->markAsRequired()
                                ->disabled($this->isViewOnly)
                                ->columnSpanFull(),

                            TextInput::make('vendor_name')
                                ->label('Vendor')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('company_name')
                                ->label('Company')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('grc_number')
                                ->label('GRC no.')
                                ->readOnly()
                                ->dehydrated(false)
                                ->visible(filled($this->invoice)),

                            DatePicker::make('invoiceAt')
                                ->label('Invoice Date')
                                ->markAsRequired()
                                ->readOnly($this->isViewOnly),

                            TagsInput::make('reference_number')
                                ->label('Reference Number')
                                ->placeholder('Enter a reference number then press enter')
                                ->splitKeys(['Enter', ','])
                                ->markAsRequired()
                                ->disabled($this->isViewOnly),

                            TextInput::make('overall_invoice_total')
                                ->label('Overall Invoice Total')
                                ->disabled()
                                ->dehydrated(false),


                        ]),
                ]),

            Repeater::make('goods_receipts')
                ->hiddenLabel()
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columnSpanFull()
                ->schema([
                    Hidden::make('good_receipt_id'),
                    Hidden::make('purchase_order'),
                    Hidden::make('good_receipt_number'),

                    Section::make('Items')
                        ->heading(fn(Get $get): string => 'Items for GR #' . ($get('good_receipt_number') ?? 'N/A'))
                        ->description('The items selected for purchase')
                        ->icon(Heroicon::OutlinedShoppingBag)
                        ->schema([
                            Repeater::make('goodReceiptItems')
                                ->hiddenLabel()
                                ->compact()
                                ->table($this->goodReceiptItemColumns())
                                ->schema([
                                    ...$this->selectCheckboxFields(),

                                    Hidden::make('p2p_sap_good_receipt_item_id'),
                                    Hidden::make('purchase_order_item'),
                                    Hidden::make('unit_cost'),

                                    TextEntry::make('purchase_order')->hiddenLabel(),
                                    TextEntry::make('gr_number')->hiddenLabel(),
                                    TextEntry::make('purchase_order_item')->hiddenLabel(),
                                    TextEntry::make('item_name')->hiddenLabel(),
                                    TextEntry::make('quantity_po')->hiddenLabel(),
                                    TextEntry::make('uom')->hiddenLabel(),
                                    TextEntry::make('quantity_served')->hiddenLabel(),

                                    TextInput::make('quantity')
                                        ->hiddenLabel()
                                        ->numeric()
                                        // Inv Qty is read-only: it mirrors the pending GR qty
                                        // and the row is included by ticking Select.
                                        ->disabled()
                                        ->dehydrated()
                                        ->required(),

                                    ...($hidePricing ? [] : [
                                        TextEntry::make('unit_cost_display')
                                            ->hiddenLabel()
                                            ->prefix('₱')
                                            ->state(fn(Get $get): string => number_format((float) ($get('unit_cost') ?? 0), 2, '.', ',')),

                                        TextEntry::make('amount_display')
                                            ->hiddenLabel()
                                            ->prefix('₱')
                                            ->state(fn(Get $get): string => number_format(
                                                (float) ($get('quantity') ?? 0) * (float) ($get('unit_cost') ?? 0),
                                                2,
                                                '.',
                                                ',',
                                            )),
                                    ]),

                                    ViewField::make('three_way')
                                        ->hiddenLabel()
                                        ->view('filament.components.three-way-match-badge')
                                        ->viewData(fn(Get $get): array => $this->threeWayBreakdown($get)),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false),

                            TextEntry::make('totals_row')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->state(fn(Get $get): HtmlString => $this->totalsRow($get('goodReceiptItems') ?? [])),
                        ]),
                ]),

            Actions::make([
                Action::make('submit')
                    ->label('Submit to AP')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(! $this->isViewOnly)
                    ->action('submit'),

                $this->escalateAction(),

                $this->cancelRequestAction(),
            ]),
        ]);
    }

    /**
     * @return Collection<int, GoodReceiptState>
     */
    #[Computed]
    public function receipts(): Collection
    {
        $query = GoodReceiptState::query()
            ->with(['sapGoodReceipt.items', 'vendor', 'company']);

        if ($invoice = $this->invoiceModel()) {
            $query->whereIn('good_receipt_number', $invoice->gr_numbers ?? []);
        } else {
            $query->whereIn('id', $this->ids);
        }

        $user = auth()->user();

        if ($user !== null && $user->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            $query->where('vendor_id', $user->vendor_id);
        }

        return $query->get();
    }

    /**
     * Data for the workflow stepper shown on an existing invoice: its status, the
     * approval history (so visited steps light up even after a return), and any open
     * escalations (rendered one stepper per GR/batch).
     *
     * @return array{status: P2pStatus|null, histories: Collection<int, ApprovalHistory>, escalations: Collection<int, Escalation>}
     */
    protected function stepperData(): array
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null) {
            return [
                'status' => null,
                'histories' => collect(),
                'escalations' => collect(),
            ];
        }

        return [
            'status' => $invoice->status,
            'histories' => $invoice->histories()
                ->with('actionedBy')
                ->orderBy('created_at')
                ->get(),
            'escalations' => $invoice->escalations()
                ->with('reason.steps')
                ->where('status', Escalation::STATUS_OPEN)
                ->orderBy('batch_number')
                ->get(),
        ];
    }

    public function submit(): void
    {
        if ($this->isViewOnly) {
            return;
        }

        $this->validate([
            'invoiceAt' => ['required', 'date'],
            'reference_number' => ['required', 'array', 'min:1'],
            'attachments' => ['required', 'array', 'min:1'],
        ]);

        $lines = $this->selectedLines();

        if ($lines === []) {
            Notification::make()->title('Select at least one line with a quantity.')->danger()->send();

            return;
        }

        $user = auth()->user();
        $service = app(P2pGoodReceiptSubmissionService::class);

        $invoice = $this->invoiceModel() ?? $service->start($user, array_map('intval', $this->ids));

        $service->submit($invoice, $lines, $user, [
            'invoice_at' => $this->invoiceAt,
            'reference_number' => array_values(array_filter($this->reference_number)),
            'attachments' => $this->persistedAttachments(),
        ]);

        Notification::make()->title('Invoice submitted to AP')->success()->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data  escalation modal state
     */
    public function escalate(array $data): void
    {
        if ($this->isViewOnly) {
            return;
        }

        $this->escalationReason = isset($data['escalationReason']) ? (string) $data['escalationReason'] : null;
        $this->escalationRemarks = (string) ($data['escalationRemarks'] ?? '');

        $this->validate([
            'escalationReason' => ['required', 'string'],
            'escalationRemarks' => ['required', 'string'],
        ]);

        $ids = $this->goodsReceiptStateIds();

        if ($ids === []) {
            Notification::make()->title('Select at least one goods receipt to escalate.')->danger()->send();

            return;
        }

        $user = auth()->user();
        $service = app(P2pGoodReceiptSubmissionService::class);

        $invoice = $this->invoiceModel() ?? $service->start($user, $ids);

        app(P2pEscalationRoutingService::class)->start(
            $invoice,
            $this->escalationReason,
            $ids,
            $user,
            $this->escalationRemarks,
        );

        Notification::make()->title('Escalation raised')->success()->send();

        $this->redirectBack();
    }

    /**
     * The item table columns. The Select column (and its checkbox) only exists while
     * the vendor can act, and pricing columns only for non-vendors, so the table
     * always stays aligned with the rendered fields.
     *
     * @return array<int, TableColumn>
     */
    protected function goodReceiptItemColumns(): array
    {
        $columns = [];

        if (! $this->isViewOnly) {
            $columns[] = TableColumn::make('Select');
        }

        $columns = array_merge($columns, [
            TableColumn::make('PO Number'),
            TableColumn::make('GR Number'),
            TableColumn::make('GR Item'),
            TableColumn::make('Short Text'),
            TableColumn::make('PO Qty'),
            TableColumn::make('Uom'),
            TableColumn::make('GR Qty'),
            TableColumn::make('Inv Qty'),
        ]);

        if (! $this->hidePricing()) {
            $columns[] = TableColumn::make('Unit Cost');
            $columns[] = TableColumn::make('Amount');
        }

        $columns[] = TableColumn::make('3-Way Matching');

        return $columns;
    }

    /**
     * @return array<int, Checkbox>
     */
    protected function selectCheckboxFields(): array
    {
        if ($this->isViewOnly) {
            return [];
        }

        return [
            Checkbox::make('include')
                ->hiddenLabel()
                ->live()
                ->afterStateUpdated(function (): void {
                    $this->recalculateTotal();
                })
                // .fi-fo-field is a CSS grid, so centre the shrink-wrapped label
                // column (the input's inline parent) to centre the box.
                ->extraFieldWrapperAttributes(['style' => 'justify-items: center;']),
        ];
    }

    /**
     * Vendors do not see pricing (unit cost / amount); admins and other roles do.
     */
    protected function hidePricing(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildGoodsReceipts(?Invoice $invoice): array
    {
        if ($invoice !== null && ! $this->isReturned) {
            return $this->billedGoodsReceipts($invoice);
        }

        return $this->billableGoodsReceipts($invoice);
    }

    /**
     * Pending (billable) groups, one per GR, with a row per SAP GR item defaulting to
     * the remaining quantity.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function billableGoodsReceipts(?Invoice $invoice): array
    {
        $receipts = $this->receipts;

        $items = $receipts->flatMap(
            fn(GoodReceiptState $state): Collection => $state->sapGoodReceipt?->items ?? collect(),
        );

        $remaining = app(P2pGoodReceiptSubmissionService::class)->remainingQuantitiesFor($items, $invoice);

        return $receipts
            ->map(fn(GoodReceiptState $state): array => [
                'good_receipt_id' => $state->id,
                'purchase_order' => $state->purchase_order,
                'good_receipt_number' => $state->good_receipt_number,
                'goodReceiptItems' => ($state->sapGoodReceipt?->items ?? collect())
                    ->map(fn(SapGoodReceiptItem $item): array => [
                        'p2p_sap_good_receipt_item_id' => $item->id,
                        'purchase_order' => $item->purchase_order,
                        'purchase_order_item' => $item->purchase_order_item,
                        'gr_number' => $state->good_receipt_number,
                        'material_doc_item' => $item->material_doc_item,
                        'item_name' => $item->item_name,
                        'uom' => $item->uom,
                        'quantity_po' => (int) $item->quantity_po,
                        'quantity_served' => (int) ($item->quantity_served ?? $item->quantity_po),
                        'unit_cost' => (float) $item->unit_cost,
                        'include' => true,
                        'quantity' => $remaining[$item->id] ?? 0,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Already-billed groups for a submitted invoice (read-only view).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function billedGoodsReceipts(Invoice $invoice): array
    {
        return $invoice->lines()
            ->with('sapItem.goodReceipt')
            ->get()
            ->groupBy(fn(InvoiceLine $line): string => (string) ($line->sapItem?->goodReceipt?->good_receipt_number ?? $line->purchase_order))
            ->map(function (Collection $lines, string $grNumber): array {
                $first = $lines->first();

                return [
                    'good_receipt_id' => $first->sapItem?->goodReceipt?->id,
                    'purchase_order' => $first->purchase_order,
                    'good_receipt_number' => $grNumber,
                    'goodReceiptItems' => $lines->map(fn(InvoiceLine $line): array => [
                        'p2p_sap_good_receipt_item_id' => $line->p2p_sap_good_receipt_item_id,
                        'purchase_order' => $line->purchase_order,
                        'purchase_order_item' => $line->purchase_order_item,
                        'gr_number' => $grNumber,
                        'material_doc_item' => $line->sapItem?->material_doc_item,
                        'item_name' => $line->item_name,
                        'uom' => $line->uom,
                        'quantity_po' => (int) $line->quantity_po,
                        'quantity_served' => (int) ($line->sapItem?->quantity_served ?? $line->quantity_po),
                        'unit_cost' => (float) $line->unit_cost,
                        'include' => true,
                        'quantity' => (int) $line->quantity_billed,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-PO-line 3-way breakdown, batched so a page with many lines issues a
     * single items query instead of one (plus an eager load) per line.
     *
     * Computed (not a stored property) so it is rebuilt on every Livewire request —
     * the 3-way badge would be empty after a checkbox toggle otherwise.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function matchBase(): array
    {
        $lineKeys = collect($this->goods_receipts)
            ->flatMap(fn(array $goodsReceipt): array => $goodsReceipt['goodReceiptItems'] ?? [])
            ->map(fn(array $item): string => $this->lineKey($item['purchase_order'] ?? null, $item['purchase_order_item'] ?? null))
            ->filter(fn(string $key): bool => $key !== '|')
            ->unique()
            ->values();

        if ($lineKeys->isEmpty()) {
            return [];
        }

        $items = SapGoodReceiptItem::query()
            ->where(function (Builder $query) use ($lineKeys): void {
                foreach ($lineKeys as $key) {
                    [$purchaseOrder, $purchaseOrderItem] = explode('|', $key, 2);

                    $query->orWhere(fn(Builder $line): Builder => $line
                        ->where('purchase_order', $purchaseOrder)
                        ->where('purchase_order_item', $purchaseOrderItem));
                }
            })
            ->with('goodReceipt')
            ->get();

        $service = app(P2pThreeWayMatchService::class);
        $reversedGoodReceiptIds = $service->reversedGoodReceiptIds($items);
        $billedByItem = $service->billedQuantitiesFor($items->pluck('id'));

        return $items
            ->groupBy(fn(SapGoodReceiptItem $item): string => $this->lineKey($item->purchase_order, $item->purchase_order_item))
            ->map(fn(Collection $lineItems): array => $service->breakdownForItems($lineItems, $reversedGoodReceiptIds, $billedByItem))
            ->all();
    }

    /**
     * Line-level 3-way breakdown for a row, with the live pending quantity added.
     *
     * @return array<string, mixed>
     */
    protected function threeWayBreakdown(Get $get): array
    {
        $key = $this->lineKey($get('purchase_order'), $get('purchase_order_item'));
        $base = $this->matchBase[$key] ?? null;

        if ($base === null) {
            return [];
        }

        $pending = $this->isViewOnly ? 0 : $this->pendingForLine($key);
        $invoiced = (int) $base['invoiced'] + $pending;

        $status = app(P2pThreeWayMatchService::class)->statusFor(
            (int) $base['ordered'],
            (int) $base['received'],
            $invoiced,
        );

        return [
            'status' => $status->value,
            'label' => $status->getLabel(),
            'color' => $status->getColor(),
            'reason' => $status->description(),
            'ordered' => (int) $base['ordered'],
            'received' => (int) $base['received'],
            'invoiced' => $invoiced,
            'adjustment' => $pending,
            'rows' => $base['rows'] ?? [],
        ];
    }

    protected function lineKey(mixed $purchaseOrder, mixed $purchaseOrderItem): string
    {
        return ((string) $purchaseOrder) . '|' . ((string) $purchaseOrderItem);
    }

    /**
     * Quantity pending submission on this screen for a PO line.
     */
    protected function pendingForLine(string $key): int
    {
        $pending = 0;

        foreach ($this->goods_receipts as $goodsReceipt) {
            foreach ($goodsReceipt['goodReceiptItems'] ?? [] as $item) {
                if ($this->lineKey($item['purchase_order'] ?? null, $item['purchase_order_item'] ?? null) !== $key) {
                    continue;
                }

                if (empty($item['include'])) {
                    continue;
                }

                $pending += (int) ($item['quantity'] ?? 0);
            }
        }

        return $pending;
    }

    /**
     * @return array<int, array{p2p_sap_good_receipt_item_id: int, quantity_billed: string}>
     */
    protected function selectedLines(): array
    {
        $lines = [];

        foreach ($this->goods_receipts as $goodsReceipt) {
            foreach ($goodsReceipt['goodReceiptItems'] ?? [] as $item) {
                if (empty($item['include']) || (float) ($item['quantity'] ?? 0) <= 0) {
                    continue;
                }

                $lines[] = [
                    'p2p_sap_good_receipt_item_id' => (int) $item['p2p_sap_good_receipt_item_id'],
                    'quantity_billed' => (string) $item['quantity'],
                ];
            }
        }

        return $lines;
    }

    /**
     * The per-GR totals rendered below the item table.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function totalsRow(array $items): HtmlString
    {
        $collection = collect($items);

        $grQty = (int) $collection->sum(fn(array $item): int => (int) ($item['quantity_served'] ?? 0));

        $selected = $collection->filter(fn(array $item): bool => ! empty($item['include']));

        $invoiceQty = (int) $selected->sum(fn(array $item): int => (int) ($item['quantity'] ?? 0));
        $amount = (float) $selected->sum(
            fn(array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0),
        );

        $amountLine = $this->hidePricing()
            ? ''
            : '<span>Total Amount: <span>₱' . number_format($amount, 2, '.', ',') . '</span></span>';

        $grQtyFmt = number_format($grQty);
        $invoiceQtyFmt = number_format($invoiceQty);

        return new HtmlString(<<<HTML
            <div style="display:flex; flex-wrap:wrap; justify-content:flex-end; gap:12px 32px; align-items:center;
                        font-weight:600; border-top:2px solid #e5e7eb; padding:12px 16px; margin-top:8px;">
                <span>Total GR Qty: <span>{$grQtyFmt}</span></span>
                <span>Total Invoice Qty: <span>{$invoiceQtyFmt}</span></span>
                {$amountLine}
            </div>
        HTML);
    }

    /**
     * Recompute the disabled "Overall Invoice Total" from the current rows.
     */
    protected function recalculateTotal(): void
    {
        $this->overall_invoice_total = $this->totalFor($this->goods_receipts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $goodsReceipts
     */
    protected function totalFor(array $goodsReceipts): string
    {
        $total = collect($goodsReceipts)
            ->flatMap(fn(array $goodsReceipt): array => $goodsReceipt['goodReceiptItems'] ?? [])
            ->filter(fn(array $item): bool => ! empty($item['include']))
            ->sum(fn(array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0));

        return number_format($total, 2, '.', ',');
    }

    /**
     * Attachments from the dehydrated schema state, so freshly uploaded files are
     * stored on the disk before the invoice references them.
     *
     * @return array<int, string>
     */
    protected function persistedAttachments(): array
    {
        $state = $this->getSchema('content')?->getState() ?? [];

        return array_values(array_filter($state['attachments'] ?? $this->attachments));
    }

    /**
     * @return array<int, int>
     */
    protected function goodsReceiptStateIds(): array
    {
        if ($invoice = $this->invoiceModel()) {
            return GoodReceiptState::query()
                ->whereIn('good_receipt_number', $invoice->gr_numbers ?? [])
                ->pluck('id')
                ->all();
        }

        return array_map('intval', $this->ids);
    }

    protected function invoiceQuery(): Builder
    {
        return Invoice::query()->with(['lines.sapItem.goodReceipt', 'vendor', 'company']);
    }

    protected function invoiceModel(): ?Invoice
    {
        if (blank($this->invoice)) {
            return null;
        }

        return $this->loadedInvoice ??= $this->invoiceQuery()->findOrFail((int) $this->invoice);
    }

    protected function authorizeInvoiceOwnership(Invoice $invoice): void
    {
        $user = auth()->user();

        if ($user?->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            abort_unless(
                (int) $invoice->vendor_id === (int) $user->vendor_id,
                403,
                'You are not authorized to view this record.',
            );
        }
    }

    protected function escalateAction(): Action
    {
        return Action::make('escalate')
            ->label('Escalate')
            ->color('warning')
            ->icon(Heroicon::OutlinedArrowTrendingUp)
            ->visible(! $this->isViewOnly)
            ->schema([
                Select::make('escalationReason')
                    ->label('Reason')
                    ->options(fn(): array => EscalationReason::query()->active()->forRole('vendor')->pluck('name', 'code')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('escalationRemarks')
                    ->label('Remarks')
                    ->required(),
            ])
            ->action(fn(array $data) => $this->escalate($data))
            ->modalSubmitActionLabel('Escalate');
    }

    protected function cancelRequestAction(): Action
    {
        return Action::make('cancelRequest')
            ->label('Cancel Request')
            ->color('danger')
            ->icon(Heroicon::OutlinedXCircle)
            ->visible((bool) $this->isReturned)
            ->requiresConfirmation()
            ->modalHeading('Cancel this request?')
            ->modalDescription('The goods receipts will go back to Pending. The cancelled request will stay in your portal history.')
            ->modalSubmitActionLabel('Yes, cancel request')
            ->modalCancelActionLabel('No, keep request')
            ->action(function (): void {
                $invoice = $this->invoiceModel();

                if ($invoice === null) {
                    return;
                }

                app(P2pGoodReceiptReturnService::class)->cancel(
                    $invoice,
                    auth()->user(),
                    'Request cancelled by vendor.',
                );

                Notification::make()
                    ->title('Request Cancelled')
                    ->body('The request has been cancelled and its goods receipts returned to Pending.')
                    ->success()
                    ->send();

                $this->redirectBack();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->action(function (): void {
                    $this->redirectBack();
                }),
        ];
    }

    protected function redirectBack(): void
    {
        $previousUrl = session('p2p_gr_resource_url');

        if (filled($previousUrl)) {
            session()->forget('p2p_gr_resource_url');
            $this->redirect($previousUrl);

            return;
        }

        $this->redirect(InvoiceResource::getUrl('index'));
    }
}
