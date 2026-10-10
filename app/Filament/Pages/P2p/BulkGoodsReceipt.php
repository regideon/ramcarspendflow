<?php

namespace App\Filament\Pages\P2p;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceiptItem;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The vendor's bulk processing screen: one submission across the selected GRs (which
 * may span several POs). Mirrors legacy BulkGoodsReceipt — submit to AP or escalate.
 */
class BulkGoodsReceipt extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'p2p/bulk-goods-receipt';

    protected static ?string $title = 'Process Goods Receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    /** @var array<int, int> */
    #[Url]
    public array $ids = [];

    public ?string $invoiceAt = null;

    public string $referenceNumbers = '';

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public ?string $escalationReason = null;

    public string $escalationRemarks = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin]) ?? false;
    }

    public function mount(): void
    {
        $this->invoiceAt = now()->toDateString();

        $this->lines = $this->items()
            ->map(fn ($item): array => [
                'p2p_sap_good_receipt_item_id' => $item->id,
                'gr_number' => $item->goodReceipt?->good_receipt_number,
                'include' => true,
                'item_name' => $item->item_name,
                'received' => (float) ($item->quantity_served ?? $item->quantity_po),
                'unit_cost' => (float) $item->unit_cost,
                'quantity' => (float) ($item->quantity_served ?? $item->quantity_po),
            ])
            ->values()
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice details')->schema([
                DatePicker::make('invoiceAt')
                    ->label('Invoice date')
                    ->required(),
                TextInput::make('referenceNumbers')
                    ->label('Reference no(s).')
                    ->helperText('Comma separated')
                    ->required(),
            ])->columns(2),

            Section::make('Goods receipt lines')->schema([
                Repeater::make('lines')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(6)
                    ->schema([
                        Hidden::make('p2p_sap_good_receipt_item_id'),
                        Toggle::make('include')
                            ->label('Bill')
                            ->inline(false),
                        TextInput::make('gr_number')
                            ->label('GR')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('item_name')
                            ->label('Item')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('received')
                            ->label('Received')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('unit_cost')
                            ->label('Unit cost')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('quantity')
                            ->label('Qty to bill')
                            ->numeric()
                            ->required(),
                    ]),
            ]),

            Section::make('Escalate instead')->schema([
                Select::make('escalationReason')
                    ->label('Reason')
                    ->options(fn (): array => $this->escalationReasons()->pluck('name', 'code')->all()),
                TextInput::make('escalationRemarks')
                    ->label('Remarks'),
            ])->columns(2),

            Actions::make([
                Action::make('submit')
                    ->label('Submit to AP')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->action('submit'),
                Action::make('escalate')
                    ->label('Escalate')
                    ->color('warning')
                    ->icon(Heroicon::OutlinedArrowTrendingUp)
                    ->action('escalate'),
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
            ->whereIn('id', $this->ids)
            ->with(['sapGoodReceipt.items.goodReceipt', 'vendor', 'company']);

        $user = auth()->user();

        if ($user !== null && $user->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            $query->where('vendor_id', $user->vendor_id);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, SapGoodReceiptItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->receipts->flatMap(fn (GoodReceiptState $state): Collection => $state->sapGoodReceipt?->items ?? collect());
    }

    /**
     * @return Collection<int, EscalationReason>
     */
    #[Computed]
    public function escalationReasons(): Collection
    {
        return EscalationReason::query()->active()->forRole('vendor')->get();
    }

    public function submit(): void
    {
        $this->validate([
            'ids' => ['required', 'array', 'min:1'],
            'invoiceAt' => ['required', 'date'],
            'referenceNumbers' => ['required', 'string'],
        ]);

        $lines = $this->selectedLines();

        if ($lines === []) {
            Notification::make()->title('Select at least one line with a quantity.')->danger()->send();

            return;
        }

        $user = auth()->user();
        $service = app(P2pGoodReceiptSubmissionService::class);

        $invoice = $service->start($user, array_map('intval', $this->ids));
        $service->submit($invoice, $lines, $user, [
            'invoice_at' => $this->invoiceAt,
            'reference_number' => array_values(array_filter(array_map('trim', explode(',', $this->referenceNumbers)))),
        ]);

        Notification::make()->title('Invoice submitted to AP')->success()->send();

        $this->redirect(InvoiceResource::getUrl('index'));
    }

    public function escalate(): void
    {
        $this->validate([
            'ids' => ['required', 'array', 'min:1'],
            'escalationReason' => ['required', 'string'],
            'escalationRemarks' => ['required', 'string'],
        ]);

        $user = auth()->user();

        $invoice = app(P2pGoodReceiptSubmissionService::class)->start($user, array_map('intval', $this->ids));
        app(P2pEscalationRoutingService::class)->start($invoice, $this->escalationReason, array_map('intval', $this->ids), $user, $this->escalationRemarks);

        Notification::make()->title('Escalation raised')->success()->send();

        $this->redirect(InvoiceResource::getUrl('index'));
    }

    /**
     * @return array<int, array{p2p_sap_good_receipt_item_id: int, quantity_billed: string}>
     */
    protected function selectedLines(): array
    {
        $lines = [];

        foreach ($this->lines as $line) {
            if (! ($line['include'] ?? false) || (float) ($line['quantity'] ?? 0) <= 0) {
                continue;
            }

            $lines[] = [
                'p2p_sap_good_receipt_item_id' => (int) $line['p2p_sap_good_receipt_item_id'],
                'quantity_billed' => (string) $line['quantity'],
            ];
        }

        return $lines;
    }
}
