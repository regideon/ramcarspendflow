<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Models\P2p\Invoice;
use App\Services\P2p\P2pThreeWayMatchService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

/**
 * Redesigned vendor "Invoices" (Google Stitch): the submissions list, start of the invoicing flow
 * (Process → {@see VendorPurchaseOrders} → {@see VendorGoodsReceipts} → {@see VendorBulkGoodsReceipt}).
 * Same tabs, columns, visibility and actions as the old list ({@see InvoiceResource}), which stays
 * under P2P (OLD) for comparison.
 */
class VendorInvoices extends Page
{
    use HasCommentsChat;
    use WithPagination;

    protected string $view = 'filament.pages.p2p.vendor.invoices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'P2P Requests';

    protected static ?string $title = 'Invoices';

    protected static ?string $slug = 'p2p/vendor-invoices';

    protected static ?int $navigationSort = 1;

    /** Same tabs and statuses as the old list ({@see ListInvoices}). */
    public const array TABS = [
        'in_progress' => 'In Progress',
        'returned' => 'Returned to Vendor',
        'cancelled' => 'Cancelled',
        'all' => 'All',
    ];

    #[Url]
    public string $tab = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?string $status = null;

    public int $perPage = 10;

    public static function canAccess(): bool
    {
        return InvoiceResource::canAccess();
    }

    public function getSubheading(): string
    {
        return 'Your submitted invoices to Ramcar AP: 3-way matching and SAP posting status.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('process')
                ->label('Process')
                ->icon(Heroicon::Plus)
                ->visible(fn (): bool => $this->isVendorOrAdmin())
                ->url(fn (): string => VendorPurchaseOrders::getUrl()),
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

    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        return $this->applyTab($this->baseQuery(), $this->tab)
            ->with(['vendor', 'company'])
            ->withUnreadCommentsCountFor(auth()->user())
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('grc_number', 'like', "%{$this->search}%")
                ->orWhere('invoice_number', 'like', "%{$this->search}%")
                ->orWhere('reference_number', 'like', "%{$this->search}%")
                ->orWhere('po_numbers', 'like', "%{$this->search}%")
                ->orWhere('gr_numbers', 'like', "%{$this->search}%")
                ->orWhereHas('vendor', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))))
            ->when(filled($this->status), fn (Builder $query) => $query->where('status', $this->status))
            ->orderByDesc('submitted_at')
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
            ->map(fn (string $label, string $key): int => $this->applyTab($this->baseQuery(), $key)->count())
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return collect(P2pStatus::cases())->mapWithKeys(fn (P2pStatus $status): array => [$status->value => $status->getLabel()])->all();
    }

    /**
     * The 3-way breakdown for a row (label, color, reason), memoised per request.
     *
     * @return array{label: string, color: string, reason: string}
     */
    public function threeWay(Invoice $invoice): array
    {
        /** @var array<int, array{label: string, color: string, reason: string}> $cache */
        static $cache = [];

        return $cache[$invoice->getKey()] ??= app(P2pThreeWayMatchService::class)->breakdownForInvoice($invoice);
    }

    /**
     * Vendors and admins open a submission on the goods-receipt screen (read-only unless it was
     * returned to them), as on the old list; others have no detail screen.
     */
    public function viewUrl(Invoice $invoice): ?string
    {
        return $this->isVendorOrAdmin() ? VendorBulkGoodsReceipt::getUrl(['invoice' => $invoice->id]) : null;
    }

    public function canResubmit(Invoice $invoice): bool
    {
        return $invoice->status === P2pStatus::ReturnedToVendor && $this->isVendor();
    }

    /** Vendors see the company column and not their own vendor name or amounts (as on the old list). */
    public function isVendor(): bool
    {
        return auth()->user()?->hasRole(RoleName::Vendor) ?? false;
    }

    /**
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'steps', 'title' => 'How invoicing works', 'body' => 'Three steps: pick your purchase orders, pick the goods receipts to bill, then attach your invoice and submit it to Ramcar AP. Process starts a new invoice.'],
            ['target' => 'tabs', 'title' => 'Your invoices', 'body' => 'In Progress are with Ramcar AP. Returned to Vendor need your fix: open one and Resubmit. Cancelled and All are for reference.'],
            ['target' => 'filters', 'title' => 'Find an invoice', 'body' => 'Search by GRC no., invoice no., reference, PO or GR, or filter by status.'],
            ['target' => 'table', 'title' => 'The list', 'body' => '3-Way Match compares your invoice with the PO and goods receipt. Invoice No is filled in by SAP once posted. Click View (or the row) to open a submission; the chat bubble holds AP\'s questions.'],
        ];
    }

    protected function isVendorOrAdmin(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin]) ?? false;
    }

    protected function baseQuery(): Builder
    {
        return InvoiceResource::getEloquentQuery();
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'in_progress' => $query->whereIn('status', [
                P2pStatus::SubmittedToAp, P2pStatus::SubmittedToChecker, P2pStatus::EscalateToProcurement,
                P2pStatus::EscalatedToWarehouse, P2pStatus::PartiallyEscalated, P2pStatus::InvoiceParked, P2pStatus::InvoicePosted,
            ]),
            'returned' => $query->whereIn('status', [P2pStatus::ReturnedToVendor, P2pStatus::ReturnedToApProcessor]),
            'cancelled' => $query->where('status', P2pStatus::Cancelled),
            default => $query,
        };
    }
}
