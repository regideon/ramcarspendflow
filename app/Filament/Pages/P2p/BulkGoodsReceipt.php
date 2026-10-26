<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pStatus;
use App\Enums\P2pThreeWayMatchStatus;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Pages\P2p\Actions\ReadInvoiceAction;
use App\Filament\Resources\P2p\Escalations\EscalationResource;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\User;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptReturnService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use App\Services\P2p\P2pInvoiceMatcher;
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
use Filament\Forms\Components\Textarea;
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
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
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
    use HasCommentsChat;
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

    /**
     * Read invoice with AI: invoices read, warnings, unmatched lines and charges (P2pInvoiceMatcher).
     *
     * @var array<string, mixed>
     */
    public array $invoiceMatch = [];

    public string $escalationRemarks = '';

    /**
     * Request-scoped invoice cache; rebuilt on the next request.
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

    /**
     * Disabled "Overall Invoice Total" input, recomputed whenever the rows change.
     */
    public string $overall_invoice_total = '0.00';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Vendor,
            RoleName::Admin,
            // Escalation hop owners open the request to Advance / Return the
            // escalation (the footer replaces the vendor actions for them).
            RoleName::Procurement,
            RoleName::Warehouse,
            RoleName::WarehouseRawMaterials,
            RoleName::WarehouseSpareParts,
        ]) ?? false;
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
            // A Filament section, styled like the RFP form's sections
            Section::make('Workflow Stage')
                ->description('Where this invoice is in the approval flow')
                ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->compact()
                ->afterHeader(fn (): array => ($status = $this->invoiceModel()?->status)
                    ? [Text::make($status->getLabel())->badge()->color($status->getColor())]
                    : [])
                ->schema([
                    View::make('filament.components.p2p-status-stepper')
                        ->viewData(fn (): array => $this->stepperData()),
                ])
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
                                ->helperText($this->isViewOnly ? null : 'Upload your invoice (PDF). Read invoice with AI fills Inv Qty and checks each line against the PO and GR.')
                                ->live()
                                ->belowContent(ReadInvoiceAction::make())
                                ->columnSpanFull(),

                            TextInput::make('vendor_name')
                                ->label('Vendor')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('company_name')
                                ->label('Company')
                                ->readOnly()
                                ->dehydrated(false),

                            // Hidden for now; keep the field so it can be re-enabled.
                            TextInput::make('grc_number')
                                ->label('GRC no.')
                                ->readOnly()
                                ->dehydrated(false)
                                ->hidden(),

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
                        ->heading(fn (Get $get): string => 'Items for GR #'.($get('good_receipt_number') ?? 'N/A').' · PO '.($get('purchase_order') ?? 'N/A'))
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
                                    Hidden::make('purchase_order'),
                                    Hidden::make('gr_number'),
                                    Hidden::make('unit_cost'),
                                    Hidden::make('quantity_remaining'),
                                    Hidden::make('ai'), // the invoice line it was matched to (Read invoice with AI)
                                    Hidden::make('quantity_edited'),

                                    TextEntry::make('purchase_order_item')->hiddenLabel(),
                                    TextEntry::make('item_short_text')->hiddenLabel(),
                                    TextEntry::make('item_name')->hiddenLabel(),
                                    // The vendor's invoice line this GR line was matched to (Read invoice with AI)
                                    ...($this->hasInvoiceMatches() ? [
                                        TextEntry::make('invoice_line')
                                            ->hiddenLabel()
                                            ->state(fn (Get $get): ?string => is_array($ai = $get('ai'))
                                                ? ($ai['description'] ?? 'On invoice').(filled($ai['invoice_number'] ?? null) ? " ({$ai['invoice_number']})" : '')
                                                : null)
                                            ->placeholder('—'),
                                    ] : []),
                                    TextEntry::make('quantity_po')->hiddenLabel(),
                                    TextEntry::make('uom')->hiddenLabel(),
                                    TextEntry::make('quantity_served')->hiddenLabel(),

                                    // Inv Qty comes from the vendor's invoice (Read invoice with AI); the
                                    // vendor may correct it, and AP sees that it was changed.
                                    TextInput::make('quantity')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(0)
                                        ->disabled($this->isViewOnly)
                                        ->dehydrated()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                            $ai = $get('ai');

                                            $set('quantity_edited', is_array($ai) && (float) $state !== (float) ($ai['quantity'] ?? 0));
                                            $set('include', (float) $state > 0);
                                            $this->recalculateTotal();
                                        }),

                                    ...($hidePricing ? [] : [
                                        TextEntry::make('unit_cost_display')
                                            ->hiddenLabel()
                                            ->prefix('₱')
                                            ->state(fn (Get $get): string => number_format((float) ($get('unit_cost') ?? 0), 2, '.', ',')),

                                        TextEntry::make('amount_display')
                                            ->hiddenLabel()
                                            ->prefix('₱')
                                            ->state(fn (Get $get): string => number_format(
                                                (float) ($get('quantity') ?? 0) * (float) ($get('unit_cost') ?? 0),
                                                2,
                                                '.',
                                                ',',
                                            )),
                                    ]),

                                    ViewField::make('three_way')
                                        ->hiddenLabel()
                                        ->view('filament.components.three-way-match-badge')
                                        ->viewData(fn (Get $get): array => $this->threeWayBreakdown($get)),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false),

                            TextEntry::make('totals_row')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->state(fn (Get $get): HtmlString => $this->totalsRow($get('goodReceiptItems') ?? [])),
                        ]),
                ]),

            Actions::make([
                $this->advanceEscalationAction(),

                $this->returnEscalationAction(),

                Action::make('submit')
                    // Says so on the button when a ticked line is not 3-way matched
                    ->label(fn (): string => $this->hasUnmatchedLines() ? 'Submit to AP (not all lines 3-way matched)' : 'Submit to AP')
                    ->color(fn (): string => $this->hasUnmatchedLines() ? 'danger' : 'primary')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(! $this->isViewOnly)
                    // Lines that don't match the invoice can still be submitted; AP is warned
                    ->requiresConfirmation(fn (): bool => $this->matchFlags() !== [])
                    ->modalHeading('Some lines need AP\'s attention')
                    ->modalDescription(fn (): HtmlString => $this->flagsSummary())
                    ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                    ->modalIconColor('warning')
                    ->modalSubmitActionLabel('Submit anyway')
                    // Check the invoice date, reference and PDF before the confirmation modal
                    // opens: its overlay hides the page's field errors, so a missing document
                    // would otherwise look like the button did nothing.
                    ->mountUsing(function (): void {
                        try {
                            $this->validateSubmissionForm();
                        } catch (ValidationException $exception) {
                            $this->notifyValidationFailure($exception);

                            // Filament unmounts the action and rethrows, so the page shows
                            // the field errors and the modal never opens.
                            throw $exception;
                        }
                    })
                    ->schema(fn (): array => $this->matchFlags() === [] ? [] : [
                        Textarea::make('match_remarks')
                            ->label('Remarks for AP (optional)')
                            ->placeholder('e.g. Price increase approved by Purchasing')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $this->submit($data['match_remarks'] ?? null);
                        } catch (ValidationException $exception) {
                            // Close the modal so the field errors underneath are visible.
                            $this->unmountAction();

                            $this->notifyValidationFailure($exception);
                        }
                    }),

                $this->escalateAction(),

                $this->cancelRequestAction(),
            ])->key('formActions'),
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
            $query->whereIn('vendor_id', $user->vendorIds());
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

    public function submit(?string $matchRemarks = null): void
    {
        if ($this->isViewOnly) {
            return;
        }

        $this->validateSubmissionForm();

        $lines = $this->selectedLines();

        if ($lines === []) {
            Notification::make()->title('Select at least one line with a quantity.')->danger()->send();

            return;
        }

        $user = auth()->user();
        $service = app(P2pGoodReceiptSubmissionService::class);

        $flags = $this->matchFlags();

        try {
            DB::transaction(function () use ($lines, $user, $flags, $matchRemarks, $service): void {
                $invoice = $this->invoiceModel() ?? $service->start($user, array_map('intval', $this->ids));

                $service->submit($invoice, $lines, $user, [
                    'invoice_at' => $this->invoiceAt,
                    'reference_number' => array_values(array_filter($this->reference_number)),
                    'attachments' => $this->persistedAttachments(),
                    'invoice_match' => [
                        ...$this->invoiceMatch,
                        'flags' => $flags,
                        'vendor_remarks' => filled($matchRemarks) ? $matchRemarks : null,
                    ],
                    'match_flag_count' => count($flags),
                ]);
            });
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure($exception);

            return;
        }

        Notification::make()->title('Invoice submitted to AP')->success()->send();

        $this->redirectBack();
    }

    /**
     * Every submission needs an invoice date, at least one reference number and the
     * invoice PDF.
     */
    protected function validateSubmissionForm(): void
    {
        $this->validate([
            'invoiceAt' => ['required', 'date'],
            'reference_number' => ['required', 'array', 'min:1'],
            'attachments' => ['required', 'array', 'min:1'],
        ]);
    }

    /**
     * Tell the vendor what stopped the submission; the field itself also keeps its
     * validation error, so the message stays visible once the modal is gone.
     */
    protected function notifyValidationFailure(ValidationException $exception): void
    {
        Notification::make()
            ->title('Could not submit')
            ->body($exception->validator?->errors()->first() ?? $exception->getMessage())
            ->danger()
            ->persistent()
            ->send();
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
            TableColumn::make('GR Item'),
            TableColumn::make('SAP Short Text'),
            TableColumn::make('Material'),
        ]);

        if ($this->hasInvoiceMatches()) {
            $columns[] = TableColumn::make('On the Invoice');
        }

        $columns = array_merge($columns, [
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
     * Whether any row has an invoice line linked (Read invoice with AI). The
     * "On the Invoice" column is mostly empty until a PDF is read, so keep it
     * out of the table until it has something to show.
     */
    protected function hasInvoiceMatches(): bool
    {
        if (collect($this->invoiceMatch['invoices'] ?? [])->isNotEmpty()) {
            return true;
        }

        return collect($this->goods_receipts ?? [])
            ->flatMap(fn (array $gr): array => $gr['goodReceiptItems'] ?? [])
            ->contains(fn (array $item): bool => is_array($item['ai'] ?? null));
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
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    // Selected by hand (no invoice read): bill what is left, flagged for AP as not checked
                    if ($state && blank($get('quantity'))) {
                        $set('quantity', $get('quantity_remaining'));
                    }

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
            fn (GoodReceiptState $state): Collection => $state->sapGoodReceipt?->items ?? collect(),
        );

        $remaining = app(P2pGoodReceiptSubmissionService::class)->remainingQuantitiesFor($items, $invoice);

        // A returned invoice keeps the lines the vendor had chosen (and what they matched on the invoice)
        $chosen = $invoice?->lines()->get()->keyBy('p2p_sap_good_receipt_item_id')->all() ?? [];

        return $receipts
            ->map(fn (GoodReceiptState $state): array => [
                'good_receipt_id' => $state->id,
                'purchase_order' => $state->purchase_order,
                'good_receipt_number' => $state->good_receipt_number,
                'goodReceiptItems' => ($state->sapGoodReceipt?->items ?? collect())
                    ->map(fn (SapGoodReceiptItem $item): array => [
                        'p2p_sap_good_receipt_item_id' => $item->id,
                        'purchase_order' => $item->purchase_order,
                        'purchase_order_item' => $item->purchase_order_item,
                        'gr_number' => $state->good_receipt_number,
                        'material_doc_item' => $item->material_doc_item,
                        'item_name' => $item->item_name,
                        'item_description' => $item->item_description,
                        'item_short_text' => $item->item_description ?: $item->item_name,
                        'uom' => $item->uom,
                        'quantity_po' => (int) $item->quantity_po,
                        'quantity_served' => (int) ($item->quantity_served ?? $item->quantity_po),
                        'unit_cost' => (float) $item->unit_cost,
                        // New invoice: nothing selected; the vendor picks the PO/GR lines on their
                        // invoice, and the 3-way badge stays "For Verification" until they do
                        'include' => isset($chosen[$item->id]),
                        // Inv Qty is empty until it is read from the invoice (or the line is selected)
                        'quantity' => isset($chosen[$item->id]) ? ($remaining[$item->id] ?? 0) : null,
                        'quantity_remaining' => $remaining[$item->id] ?? 0,
                        'ai' => isset($chosen[$item->id]) ? $this->savedMatch($chosen[$item->id]) : null,
                        'quantity_edited' => (bool) ($chosen[$item->id]->is_quantity_edited ?? false),
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
            ->groupBy(fn (InvoiceLine $line): string => (string) ($line->sapItem?->goodReceipt?->good_receipt_number ?? $line->purchase_order))
            ->map(function (Collection $lines, string $grNumber): array {
                $first = $lines->first();

                return [
                    'good_receipt_id' => $first->sapItem?->goodReceipt?->id,
                    'purchase_order' => $first->purchase_order,
                    'good_receipt_number' => $grNumber,
                    'goodReceiptItems' => $lines->map(fn (InvoiceLine $line): array => [
                        'p2p_sap_good_receipt_item_id' => $line->p2p_sap_good_receipt_item_id,
                        'purchase_order' => $line->purchase_order,
                        'purchase_order_item' => $line->purchase_order_item,
                        'gr_number' => $grNumber,
                        'material_doc_item' => $line->sapItem?->material_doc_item,
                        'item_name' => $line->item_name,
                        'item_description' => $line->item_description ?: $line->sapItem?->item_description,
                        'item_short_text' => $line->item_description
                            ?: $line->sapItem?->item_description
                            ?: $line->item_name,
                        'uom' => $line->uom,
                        'quantity_po' => (int) $line->quantity_po,
                        'quantity_served' => (int) ($line->sapItem?->quantity_served ?? $line->quantity_po),
                        'unit_cost' => (float) $line->unit_cost,
                        'include' => true,
                        'quantity' => (int) $line->quantity_billed,
                        'quantity_remaining' => (int) $line->quantity_billed,
                        'ai' => $this->savedMatch($line),
                        'quantity_edited' => (bool) $line->is_quantity_edited,
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
            ->flatMap(fn (array $goodsReceipt): array => $goodsReceipt['goodReceiptItems'] ?? [])
            ->map(fn (array $item): string => $this->lineKey($item['purchase_order'] ?? null, $item['purchase_order_item'] ?? null))
            ->filter(fn (string $key): bool => $key !== '|')
            ->unique()
            ->values();

        if ($lineKeys->isEmpty()) {
            return [];
        }

        $items = SapGoodReceiptItem::query()
            ->where(function (Builder $query) use ($lineKeys): void {
                foreach ($lineKeys as $key) {
                    [$purchaseOrder, $purchaseOrderItem] = explode('|', $key, 2);

                    $query->orWhere(fn (Builder $line): Builder => $line
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
            ->groupBy(fn (SapGoodReceiptItem $item): string => $this->lineKey($item->purchase_order, $item->purchase_order_item))
            ->map(fn (Collection $lineItems): array => $service->breakdownForItems($lineItems, $reversedGoodReceiptIds, $billedByItem))
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

        $row = $this->rowMatch([
            'include' => $get('include'),
            'quantity' => $get('quantity'),
            'quantity_remaining' => $get('quantity_remaining'),
            'ai' => $get('ai'),
            'quantity_edited' => $get('quantity_edited'),
        ], $status);
        $status = $row['status'];

        return [
            'status' => $status->value,
            'label' => $status->getLabel(),
            'color' => $status->getColor(),
            'reason' => $status->description(),
            'notes' => $row['notes'],
            'ordered' => (int) $base['ordered'],
            'received' => (int) $base['received'],
            'invoiced' => $invoiced,
            'adjustment' => $pending,
            'rows' => $base['rows'] ?? [],
        ];
    }

    /**
     * A row's 3-way status with what the vendor's invoice said: the quantity status from the
     * PO/GR/invoice quantities, No Match when the invoice price or currency is off, and the
     * notes shown in the badge. "flags" are what the AP Processor is warned about.
     *
     * @param  array<string, mixed>  $row
     * @return array{status: P2pThreeWayMatchStatus, notes: list<string>, flags: list<string>}
     */
    public function rowMatch(array $row, ?P2pThreeWayMatchStatus $quantityStatus = null): array
    {
        $quantityStatus ??= $this->quantityStatusFor($row);
        $ai = is_array($row['ai'] ?? null) ? $row['ai'] : null;
        $quantity = (float) ($row['quantity'] ?? 0);

        if (empty($row['include']) || $quantity <= 0) {
            return ['status' => $quantityStatus, 'notes' => [], 'flags' => []];
        }

        // Submitted lines show what was recorded at submission
        if ($this->isViewOnly) {
            return [
                'status' => P2pThreeWayMatchStatus::tryFrom((string) ($ai['saved']['status'] ?? '')) ?? $quantityStatus,
                'notes' => $ai['saved']['reasons'] ?? [],
                'flags' => [],
            ];
        }

        if ($ai === null) {
            $note = 'Not checked against an invoice: selected by hand.';

            return ['status' => $quantityStatus, 'notes' => [$note], 'flags' => [$note]];
        }

        $remaining = (int) ($row['quantity_remaining'] ?? 0);
        $quantityReasons = app(P2pInvoiceMatcher::class)->quantityReasons($quantity, $remaining);
        $priceReasons = $ai['price_reasons'] ?? [];

        $status = ($ai['price_ok'] ?? null) === false ? P2pThreeWayMatchStatus::NoMatch : $quantityStatus;

        $notes = array_values(array_filter([
            sprintf(
                'Invoice %s: %s%s',
                $ai['invoice_number'] ?? '—',
                rtrim(rtrim(number_format((float) ($ai['quantity'] ?? 0), 4, '.', ''), '0'), '.'),
                isset($ai['unit_price']) ? sprintf(' @ %s %s', $ai['currency'] ?? '', number_format((float) $ai['unit_price'], 2)) : '',
            ),
            ...$quantityReasons,
            ...$priceReasons,
            ...($ai['notes'] ?? []),
        ]));

        $flags = $status === P2pThreeWayMatchStatus::NoMatch
            ? [...($quantity > $remaining ? $quantityReasons : []), ...$priceReasons]
            : [];

        if (! empty($row['quantity_edited'])) {
            $edited = sprintf('Inv Qty changed by the vendor (the invoice says %s).', rtrim(rtrim(number_format((float) ($ai['quantity'] ?? 0), 4, '.', ''), '0'), '.'));
            $notes[] = $edited;
            $flags[] = $edited;
        }

        return ['status' => $status, 'notes' => $notes, 'flags' => array_values(array_unique($flags))];
    }

    /**
     * Whether a ticked line does not match the invoice (or was not checked against one).
     */
    public function hasUnmatchedLines(): bool
    {
        return $this->matchFlags() !== [];
    }

    /**
     * Selected lines the AP Processor should check, with why.
     *
     * @return list<array{gr_number: ?string, purchase_order: ?string, purchase_order_item: ?string, item_name: ?string, reasons: list<string>}>
     */
    public function matchFlags(): array
    {
        $flags = [];

        foreach ($this->goods_receipts as $goodsReceipt) {
            foreach ($goodsReceipt['goodReceiptItems'] ?? [] as $item) {
                $reasons = $this->rowMatch($item)['flags'];

                if ($reasons !== []) {
                    $flags[] = [
                        'gr_number' => $item['gr_number'] ?? null,
                        'purchase_order' => $item['purchase_order'] ?? null,
                        'purchase_order_item' => $item['purchase_order_item'] ?? null,
                        'item_name' => $item['item_name'] ?? null,
                        'reasons' => $reasons,
                    ];
                }
            }
        }

        return $flags;
    }

    protected function flagsSummary(): HtmlString
    {
        $items = collect($this->matchFlags())
            ->map(fn (array $flag): string => sprintf(
                '<li><strong>GR %s · %s</strong>: %s</li>',
                e($flag['gr_number']),
                e($flag['item_name']),
                e(implode(' ', $flag['reasons'])),
            ))
            ->implode('');

        return new HtmlString(
            '<p>You can still submit. The AP Processor will see these lines flagged, with your remarks.</p>'
            ."<ul style=\"margin-top:8px;padding-left:18px;list-style:disc;text-align:left;\">{$items}</ul>",
        );
    }

    /**
     * The quantity-only 3-way status of a row (PO line totals plus what is pending on screen).
     *
     * @param  array<string, mixed>  $row
     */
    protected function quantityStatusFor(array $row): P2pThreeWayMatchStatus
    {
        $key = $this->lineKey($row['purchase_order'] ?? null, $row['purchase_order_item'] ?? null);
        $base = $this->matchBase[$key] ?? null;

        if ($base === null) {
            return P2pThreeWayMatchStatus::ForVerification;
        }

        $pending = $this->isViewOnly ? 0 : $this->pendingForLine($key);

        return app(P2pThreeWayMatchService::class)->statusFor((int) $base['ordered'], (int) $base['received'], (int) $base['invoiced'] + $pending);
    }

    /**
     * What a submitted line recorded about the invoice, for the badge.
     *
     * @return array<string, mixed>|null
     */
    protected function savedMatch(InvoiceLine $line): ?array
    {
        if ($line->match_status === null) {
            return null;
        }

        return [
            'invoice_number' => $line->invoice_reference,
            'quantity' => (float) $line->quantity_billed,
            'unit_price' => $line->invoice_unit_price !== null ? (float) $line->invoice_unit_price : null,
            'currency' => $line->invoice_currency,
            'price_ok' => null,
            'price_reasons' => [],
            'notes' => $line->is_invoice_verified ? ($line->match_reasons ?? []) : [],
            'saved' => ['status' => $line->match_status->value, 'reasons' => $line->match_reasons ?? []],
            'verified' => $line->is_invoice_verified,
        ];
    }

    /**
     * The returned invoice being edited, if any (excluded from billed quantities when matching).
     */
    public function currentInvoice(): ?Invoice
    {
        return $this->invoiceModel();
    }

    /**
     * GR lines on screen keyed by mirror item id, for the review.
     *
     * @return array<int, array{gr_number: ?string, purchase_order: ?string, purchase_order_item: ?string, item_name: ?string, uom: ?string, quantity_po: int, quantity_served: int, quantity_remaining: int}>
     */
    public function rowLabels(): array
    {
        return collect($this->goods_receipts)
            ->flatMap(fn (array $goodsReceipt): array => $goodsReceipt['goodReceiptItems'] ?? [])
            ->mapWithKeys(fn (array $item): array => [(int) $item['p2p_sap_good_receipt_item_id'] => [
                'gr_number' => $item['gr_number'] ?? null,
                'purchase_order' => $item['purchase_order'] ?? null,
                'purchase_order_item' => $item['purchase_order_item'] ?? null,
                'item_name' => $item['item_name'] ?? null,
                'uom' => $item['uom'] ?? null,
                'quantity_po' => (int) ($item['quantity_po'] ?? 0),
                'quantity_served' => (int) ($item['quantity_served'] ?? 0),
                'quantity_remaining' => (int) ($item['quantity_remaining'] ?? 0),
            ]])
            ->all();
    }

    /**
     * GR items on screen, for matching the vendor's invoice.
     *
     * @return Collection<int, SapGoodReceiptItem>
     */
    public function itemsOnScreen(): Collection
    {
        $ids = collect($this->goods_receipts)
            ->flatMap(fn (array $goodsReceipt): array => $goodsReceipt['goodReceiptItems'] ?? [])
            ->pluck('p2p_sap_good_receipt_item_id')
            ->filter()
            ->all();

        return SapGoodReceiptItem::query()->with(['item', 'goodReceipt'])->whereKey($ids)->get();
    }

    /**
     * Apply a reviewed invoice match: select the matched rows, fill Inv Qty, keep what the invoice
     * said for the badge, and add the invoice numbers and date.
     *
     * @param  array<string, mixed>  $match  P2pInvoiceMatcher::match() result
     * @param  list<int>|null  $only  GR item ids the vendor ticked in the review (null: every matched line)
     */
    public function applyInvoiceMatch(array $match, ?array $only = null): void
    {
        $rows = $match['rows'] ?? [];

        if ($only !== null) {
            $rows = array_intersect_key($rows, array_flip(array_map('intval', $only)));
        }

        foreach ($this->goods_receipts as $g => $goodsReceipt) {
            foreach ($goodsReceipt['goodReceiptItems'] ?? [] as $i => $item) {
                $row = $rows[$item['p2p_sap_good_receipt_item_id']] ?? null;

                // Not ticked or not on the invoice: the line is left as it is
                if ($row === null) {
                    continue;
                }

                $quantity = (float) $row['quantity'];

                $this->goods_receipts[$g]['goodReceiptItems'][$i] = [
                    ...$item,
                    'include' => $quantity > 0,
                    'quantity' => floor($quantity) === $quantity ? (int) $quantity : $quantity,
                    'quantity_edited' => false,
                    'ai' => collect($row)->only(['invoice_number', 'invoice_date', 'description', 'quantity', 'unit_price', 'currency', 'price_ok', 'price_reasons', 'notes'])->all(),
                ];
            }
        }

        $numbers = collect($match['invoices'] ?? [])->pluck('invoice_number')->filter()->all();
        $this->reference_number = array_values(array_unique([...array_filter($this->reference_number), ...$numbers]));

        $date = collect($match['invoices'] ?? [])->pluck('invoice_date')->first(fn (?string $date): bool => filled($date) && strtotime($date) !== false);

        if ($date !== null) {
            $this->invoiceAt = $date;
        }

        $this->invoiceMatch = [
            'read_at' => now()->toIso8601String(),
            ...collect($match)->only(['invoices', 'warnings', 'unmatched_lines', 'charges', 'files'])->all(),
        ];

        unset($this->matchBase);
        $this->recalculateTotal();
    }

    protected function lineKey(mixed $purchaseOrder, mixed $purchaseOrderItem): string
    {
        return ((string) $purchaseOrder).'|'.((string) $purchaseOrderItem);
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

                $match = $this->rowMatch($item);
                $ai = is_array($item['ai'] ?? null) ? $item['ai'] : null;

                $lines[] = [
                    'p2p_sap_good_receipt_item_id' => (int) $item['p2p_sap_good_receipt_item_id'],
                    'quantity_billed' => (string) $item['quantity'],
                    'invoice_reference' => $ai['invoice_number'] ?? null,
                    'invoice_unit_price' => $ai['unit_price'] ?? null,
                    'invoice_currency' => $ai['currency'] ?? null,
                    'match_status' => $match['status']->value,
                    'match_reasons' => $match['notes'],
                    'is_invoice_verified' => $ai !== null,
                    'is_quantity_edited' => (bool) ($item['quantity_edited'] ?? false),
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

        $grQty = (int) $collection->sum(fn (array $item): int => (int) ($item['quantity_served'] ?? 0));

        $selected = $collection->filter(fn (array $item): bool => ! empty($item['include']));

        $invoiceQty = (int) $selected->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0));
        $amount = (float) $selected->sum(
            fn (array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0),
        );

        $amountLine = $this->hidePricing()
            ? ''
            : '<span>Total Amount: <span>₱'.number_format($amount, 2, '.', ',').'</span></span>';

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
            ->flatMap(fn (array $goodsReceipt): array => $goodsReceipt['goodReceiptItems'] ?? [])
            ->filter(fn (array $item): bool => ! empty($item['include']))
            ->sum(fn (array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0));

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

    /**
     * The chat bubble opens the open invoice's thread; without one it shows the inbox.
     */
    protected function commentsChatRecord(): ?Invoice
    {
        return $this->invoiceModel();
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
                in_array((int) $invoice->vendor_id, $user->vendorIds(), true),
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
                    ->options(fn (): array => EscalationReason::query()->active()->forRole('vendor')->pluck('name', 'code')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('escalationRemarks')
                    ->label('Remarks')
                    ->required(),
            ])
            ->action(fn (array $data) => $this->escalate($data))
            ->modalSubmitActionLabel('Escalate');
    }

    /**
     * The request's open escalation whose current hop is owned by the signed-in
     * user, if any. When present, the footer becomes Advance / Return and the
     * vendor actions are hidden (the request is out on an escalation).
     */
    protected function escalationForUser(): ?Escalation
    {
        if ($this->escalationForUserResolved) {
            return $this->escalationForUser;
        }

        $this->escalationForUserResolved = true;

        $user = auth()->user();
        $invoice = $this->invoiceModel();

        if (! $user instanceof User || $invoice === null) {
            return null;
        }

        return $this->escalationForUser = $invoice->escalations()
            ->where('status', Escalation::STATUS_OPEN)
            ->with(['reason.steps'])
            ->get()
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
            ->visible(fn (): bool => $this->escalationForUser() !== null)
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
            ->visible(fn (): bool => $this->escalationForUser() !== null)
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

        app(P2pEscalationRoutingService::class)->advance($escalation, auth()->user(), (string) ($data['remarks'] ?? ''));

        Notification::make()
            ->title('Escalation advanced')
            ->body('The escalation has been forwarded to the next step.')
            ->success()
            ->send();

        $this->redirect(EscalationResource::getUrl('index'));
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

        app(P2pEscalationRoutingService::class)->returnToPreviousHop($escalation, auth()->user(), (string) ($data['remarks'] ?? ''));

        Notification::make()
            ->title('Escalation returned')
            ->body('The escalation has been sent back to the previous step.')
            ->success()
            ->send();

        $this->redirect(EscalationResource::getUrl('index'));
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
