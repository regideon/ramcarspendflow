<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\PurchaseOrders\Actions\SyncFromSapAction;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapPurchaseOrder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Redesigned vendor "Purchase Orders" (Google Stitch), step 1 of invoicing: pick POs, then
 * Process GRs opens {@see VendorGoodsReceipts}. Same data, tabs and actions as the old list
 * ({@see PurchaseOrderResource}), which stays at its own URL for comparison.
 */
class VendorPurchaseOrders extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.p2p.vendor.purchase-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $title = 'Purchase Orders';

    protected static ?string $slug = 'p2p/vendor-purchase-orders';

    /** Reached from Invoices › Process, as the old list. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same tabs as the old list: status_id 0 Pending, 1 Completed, 2 Partially Submitted, 3 Fully Submitted. */
    public const array TABS = [
        'all' => 'All',
        'pending_and_partial' => 'Pending & Partially Submitted',
        'fully_submitted' => 'Fully Submitted',
        'completed' => 'Completed',
    ];

    public const array SORTABLE = ['purchase_order', 'purchase_order_date', 'quantity_po_balance', 'changed_on'];

    #[Url]
    public string $tab = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?string $status = null;

    #[Url]
    public string $sort = 'changed_on';

    #[Url]
    public string $direction = 'desc';

    public int $perPage = 10;

    /** @var list<string> PO numbers ticked for Process GRs */
    public array $selected = [];

    public static function canAccess(): bool
    {
        return PurchaseOrderResource::canAccess();
    }

    public function getSubheading(): string
    {
        return 'Select purchase orders to review their goods receipts and start an invoice.';
    }

    protected function getHeaderActions(): array
    {
        return [
            SyncFromSapAction::make()
                ->outlined()
                ->extraAttributes(['class' => 'sf-btn-soft']),
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'all';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'desc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->resetPage();
    }

    /**
     * @param  list<string>  $purchaseOrders
     */
    public function togglePage(array $purchaseOrders): void
    {
        $this->selected = array_diff($purchaseOrders, $this->selected) === []
            ? array_values(array_diff($this->selected, $purchaseOrders))
            : array_values(array_unique([...$this->selected, ...$purchaseOrders]));
    }

    /** Process GRs: the goods receipts of the ticked POs (vendors only, as on the old list). */
    public function processGrs(): void
    {
        if (! $this->canProcess() || $this->selected === []) {
            return;
        }

        $this->redirect(VendorGoodsReceipts::getUrl(['purchase_orders' => array_values($this->selected)]));
    }

    public function canProcess(): bool
    {
        return auth()->user()?->hasRole(RoleName::Vendor) ?? false;
    }

    #[Computed]
    public function purchaseOrders(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'changed_on';

        return $this->applyTab(PurchaseOrderResource::getEloquentQuery(), $this->tab)
            ->with('company')
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('purchase_order', 'like', "%{$this->search}%")
                ->orWhere('vendor_code', 'like', "%{$this->search}%")
                ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))))
            ->when(filled($this->status), fn (Builder $query) => $query->where('status_id', $this->status))
            ->orderBy($sort, $this->direction === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        return collect(self::TABS)
            ->map(fn (string $label, string $key): int => $this->applyTab(PurchaseOrderResource::getEloquentQuery(), $key)->count())
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return collect(P2pPurchaseOrderStatus::cases())->mapWithKeys(fn (P2pPurchaseOrderStatus $status): array => [$status->value => $status->getLabel()])->all();
    }

    /**
     * POs with a pending goods receipt open on the Pending tab, the rest on All (as on the old list).
     *
     * @return array<string, true>
     */
    #[Computed]
    public function purchaseOrdersWithPendingGrs(): array
    {
        return GoodReceiptState::query()
            ->where('status', P2pStatus::Pending)
            ->whereIn('purchase_order', $this->purchaseOrders->pluck('purchase_order'))
            ->distinct()
            ->pluck('purchase_order')
            ->mapWithKeys(fn (string $po): array => [$po => true])
            ->all();
    }

    /** "View GR's" and the row: that PO's goods receipts. */
    public function goodsReceiptsUrl(SapPurchaseOrder $po): string
    {
        return VendorGoodsReceipts::getUrl([
            'activeTab' => isset($this->purchaseOrdersWithPendingGrs[$po->purchase_order]) ? 'pending' : 'all',
            'purchase_orders' => [$po->purchase_order],
        ]);
    }

    /**
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'steps', 'title' => 'Step 1 of 3', 'body' => 'Pick the purchase orders you want to bill. Next you choose their goods receipts, then attach your invoice.'],
            ['target' => 'tabs', 'title' => 'Which POs to bill', 'body' => 'Pending & Partially Submitted still have goods receipts to bill. Fully Submitted are all billed and waiting on AP; Completed are done.'],
            ['target' => 'table', 'title' => 'Pick and process', 'body' => 'Tick one or more POs and click Process GRs, or click View GR\'s on a row to see that PO\'s goods receipts. PO balance is what is left to bill.'],
        ];
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'pending_and_partial' => $query->whereIn('status_id', [P2pPurchaseOrderStatus::Pending, P2pPurchaseOrderStatus::PartiallySubmitted]),
            'fully_submitted' => $query->where('status_id', P2pPurchaseOrderStatus::FullySubmitted),
            'completed' => $query->where('status_id', P2pPurchaseOrderStatus::Completed),
            default => $query,
        };
    }
}
