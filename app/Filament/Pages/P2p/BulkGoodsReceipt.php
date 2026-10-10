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
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
    protected string $view = 'filament.pages.p2p.bulk-goods-receipt';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'p2p/bulk-goods-receipt';

    protected static ?string $title = 'Process Goods Receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    /** @var array<int, int> */
    #[Url]
    public array $ids = [];

    public ?string $invoiceAt = null;

    public string $referenceNumbers = '';

    /** @var array<int, bool> keyed by SAP GR item id */
    public array $selected = [];

    /** @var array<int, string> keyed by SAP GR item id */
    public array $quantities = [];

    public ?string $escalationReason = null;

    public string $escalationRemarks = '';

    public function mount(): void
    {
        $this->invoiceAt = now()->toDateString();

        foreach ($this->items() as $item) {
            $this->selected[$item->id] = true;
            $this->quantities[$item->id] = (string) ($item->quantity_served ?? $item->quantity_po);
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin]) ?? false;
    }

    /**
     * @return Collection<int, GoodReceiptState>
     */
    #[Computed]
    public function receipts(): Collection
    {
        $query = GoodReceiptState::query()
            ->whereIn('id', $this->ids)
            ->with(['sapGoodReceipt.items', 'vendor', 'company']);

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

    public function submit(P2pGoodReceiptSubmissionService $service): void
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

        $invoice = $service->start($user, array_map('intval', $this->ids));
        $service->submit($invoice, $lines, $user, [
            'invoice_at' => $this->invoiceAt,
            'reference_number' => array_values(array_filter(array_map('trim', explode(',', $this->referenceNumbers)))),
        ]);

        Notification::make()->title('Invoice submitted to AP')->success()->send();

        $this->redirect(InvoiceResource::getUrl('index'));
    }

    public function escalate(P2pGoodReceiptSubmissionService $submission, P2pEscalationRoutingService $escalations): void
    {
        $this->validate([
            'ids' => ['required', 'array', 'min:1'],
            'escalationReason' => ['required', 'string'],
            'escalationRemarks' => ['required', 'string'],
        ]);

        $user = auth()->user();
        $invoice = $submission->start($user, array_map('intval', $this->ids));

        $escalations->start($invoice, $this->escalationReason, array_map('intval', $this->ids), $user, $this->escalationRemarks);

        Notification::make()->title('Escalation raised')->success()->send();

        $this->redirect(InvoiceResource::getUrl('index'));
    }

    /**
     * @return array<int, array{p2p_sap_good_receipt_item_id: int, quantity_billed: string}>
     */
    protected function selectedLines(): array
    {
        $lines = [];

        foreach ($this->items() as $item) {
            if (! ($this->selected[$item->id] ?? false)) {
                continue;
            }

            $quantity = $this->quantities[$item->id] ?? '0';

            if ((float) $quantity > 0) {
                $lines[] = ['p2p_sap_good_receipt_item_id' => $item->id, 'quantity_billed' => (string) $quantity];
            }
        }

        return $lines;
    }
}
