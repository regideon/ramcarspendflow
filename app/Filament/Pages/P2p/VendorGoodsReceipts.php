<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\P2p\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Models\P2p\GoodReceiptState;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Redesigned vendor "Goods Receipts" (Google Stitch), step 2 of invoicing: the GRs of the POs
 * picked on {@see VendorPurchaseOrders}; Process / Process Selected opens {@see VendorBulkGoodsReceipt}
 * (step 3). Same tabs, scope and actions as the old list
 * ({@see ListGoodsReceipts}), which stays for comparison.
 */
class VendorGoodsReceipts extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.p2p.vendor.goods-receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $title = 'Goods Receipts';

    protected static ?string $slug = 'p2p/vendor-goods-receipts';

    /** Reached from Purchase Orders, as the old list. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same tabs and statuses as the old list. */
    public const array TABS = [
        'pending' => 'Pending',
        'submitted_ap' => 'Submitted to AP',
        'escalated' => 'Escalate to Procurement',
        'rejected' => 'Rejected/Reversed',
        'returned' => 'Returned to Vendor',
        'posted' => 'Posted to SAP',
        'all' => 'All',
    ];

    /** Same query-string name as the old list's deep links. */
    #[Url(as: 'activeTab')]
    public string $tab = 'pending';

    /** @var list<string> */
    #[Url(as: 'purchase_orders')]
    public array $purchaseOrders = [];

    /** @var list<string> */
    #[Url(as: 'gr_numbers')]
    public array $grNumbers = [];

    #[Url(as: 'q')]
    public string $search = '';

    public int $perPage = 10;

    /** @var list<string> GR state ids ticked for Process Selected */
    public array $selected = [];

    public static function canAccess(): bool
    {
        return GoodsReceiptResource::canAccess();
    }

    public function mount(): void
    {
        $this->purchaseOrders = $this->clean($this->purchaseOrders);
        $this->grNumbers = $this->clean($this->grNumbers);

        // The goods-receipt screen sends the vendor back here when they are done (as the old list does).
        session(['p2p_gr_resource_url' => request()->fullUrl()]);
    }

    public function getSubheading(): string
    {
        return 'Goods receipts the Ramcar warehouse posted for your purchase orders. Pick the ones to bill on one invoice.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Purchase Orders')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(VendorPurchaseOrders::getUrl()),
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'pending';
        $this->resetPage();
        $this->selected = [];
    }

    public function removePurchaseOrder(string $purchaseOrder): void
    {
        $this->purchaseOrders = array_values(array_diff($this->purchaseOrders, [$purchaseOrder]));
        $this->resetPage();
        $this->selected = [];
    }

    public function resetScope(): void
    {
        $this->reset('purchaseOrders', 'grNumbers', 'search');
        $this->resetPage();
        $this->selected = [];
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function togglePage(array $ids): void
    {
        $ids = array_map('strval', $ids);

        $this->selected = array_diff($ids, $this->selected) === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    /** Process Selected: the ticked GRs on one invoice (vendors only, as on the old list). */
    public function processSelected(): void
    {
        if (! $this->isVendor() || $this->selected === []) {
            return;
        }

        $this->redirect(VendorBulkGoodsReceipt::getUrl(['ids' => array_map('intval', $this->selected)]));
    }

    public function processUrl(GoodReceiptState $receipt): string
    {
        return VendorBulkGoodsReceipt::getUrl(['ids' => [$receipt->id]]);
    }

    public function canProcess(GoodReceiptState $receipt): bool
    {
        return $this->isVendor() && $receipt->status?->isEditableByVendor();
    }

    /** Vendors don't see pricing on their own goods receipts (as on the old list). */
    public function isVendor(): bool
    {
        return auth()->user()?->hasRole(RoleName::Vendor) ?? false;
    }

    #[Computed]
    public function receipts(): LengthAwarePaginator
    {
        return $this->applyTab($this->scopedQuery(), $this->tab)
            ->with('vendor')
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('good_receipt_number', 'like', "%{$this->search}%")
                ->orWhere('purchase_order', 'like', "%{$this->search}%")))
            ->orderByDesc('updated_at')
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
            ->map(fn (string $label, string $key): int => $this->applyTab($this->scopedQuery(), $key)->count())
            ->all();
    }

    /**
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'steps', 'title' => 'Step 2 of 3', 'body' => 'Pick the goods receipts to bill. Next you attach your invoice, and AI checks it against the PO and GR.'],
            ['target' => 'scope', 'title' => 'Your purchase orders', 'body' => 'Only the goods receipts of the POs you picked are listed. Remove a PO chip to drop it, or Reset to see all.'],
            ['target' => 'tabs', 'title' => 'Where each receipt is', 'body' => 'Pending receipts are ready to bill. The other tabs show receipts already submitted, escalated, returned or posted.'],
            ['target' => 'table', 'title' => 'Pick and process', 'body' => 'Tick the receipts for one invoice and click Process Selected, or Process a single receipt. Click any row to open it.'],
        ];
    }

    /**
     * The vendor's GRs, limited to the picked POs / GR numbers (a GR "-P1" part also matches its base number).
     */
    protected function scopedQuery(): Builder
    {
        $query = GoodsReceiptResource::getEloquentQuery();

        if ($this->purchaseOrders === [] && $this->grNumbers === []) {
            return $query;
        }

        return $query->where(function (Builder $query): void {
            if ($this->purchaseOrders !== []) {
                $query->orWhereIn('purchase_order', $this->purchaseOrders);
            }

            foreach ($this->grNumbers as $grNumber) {
                $base = (string) preg_replace('/-P\d+$/', '', $grNumber);
                $query->orWhere('good_receipt_number', $grNumber)
                    ->orWhere('good_receipt_number', $base)
                    ->orWhere('good_receipt_number', 'like', $base.'-P%');
            }
        });
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'pending' => $query->where('status', P2pStatus::Pending),
            'submitted_ap' => $query->where('status', P2pStatus::SubmittedToAp),
            'escalated' => $query->whereIn('status', [P2pStatus::EscalateToProcurement, P2pStatus::EscalatedToWarehouse, P2pStatus::PartiallyEscalated]),
            'rejected' => $query->whereIn('status', [P2pStatus::Rejected, P2pStatus::RejectedLegacy, P2pStatus::Reversed]),
            'returned' => $query->where('status', P2pStatus::ReturnedToVendor),
            'posted' => $query->whereIn('status', [P2pStatus::InvoiceParked, P2pStatus::InvoicePosted, P2pStatus::Completed]),
            default => $query,
        };
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    protected function clean(array $values): array
    {
        return array_values(array_filter(array_map(fn (mixed $value): string => (string) $value, $values), 'filled'));
    }
}
