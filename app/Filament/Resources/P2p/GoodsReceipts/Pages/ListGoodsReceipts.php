<?php

namespace App\Filament\Resources\P2p\GoodsReceipts\Pages;

use App\Enums\P2pStatus;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    /**
     * GR numbers to scope the list to, deep-linked from the Purchase Orders table.
     *
     * @var array<int, string>|null
     */
    #[Url]
    public ?array $gr_numbers = null;

    /**
     * Purchase orders to scope the list to. This is the robust link —
     * GoodReceiptState.purchase_order is always populated, unlike
     * SapPurchaseOrder.gr_numbers which can be stale.
     *
     * @var array<int, string>|null
     */
    #[Url]
    public ?array $purchase_orders = null;

    public function mount(): void
    {
        parent::mount();

        // #[Url] hydrates these on a fresh load; the request fallback keeps scalar
        // (?gr_numbers=5000000001) and array links both working.
        $this->gr_numbers = $this->normaliseFilter(request()->query('gr_numbers')) ?? $this->gr_numbers;
        $this->purchase_orders = $this->normaliseFilter(request()->query('purchase_orders')) ?? $this->purchase_orders;

        // The Purchase Orders table deep-links the target tab as `activeTab`; Filament's
        // native tab property is bound to `tab`. Accept either.
        if (is_string($activeTab = request()->query('activeTab')) && filled($activeTab)) {
            $this->activeTab = $activeTab;
        }

        session([
            'gr_numbers_filter' => $this->gr_numbers,
            'gr_purchase_orders_filter' => $this->purchase_orders,
            // The bulk processing screen redirects back here when it is done.
            'p2p_gr_resource_url' => request()->fullUrl(),
        ]);

        // Land on Pending by default so the URL always reflects the visible tab.
        if (! request()->has('activeTab') && ! request()->has('tab')) {
            $queryParams = ['activeTab' => 'pending'];

            if ($this->gr_numbers) {
                $queryParams['gr_numbers'] = $this->gr_numbers;
            }

            if ($this->purchase_orders) {
                $queryParams['purchase_orders'] = $this->purchase_orders;
            }

            $this->redirect(request()->url().'?'.http_build_query($queryParams));
        }
    }

    public function updatedGrNumbers(): void
    {
        session(['gr_numbers_filter' => $this->gr_numbers]);

        $this->resetTable();
    }

    public function updatedPurchaseOrders(): void
    {
        session(['gr_purchase_orders_filter' => $this->purchase_orders]);

        $this->resetTable();
    }

    /**
     * Status tabs mirroring the legacy Goods Receipts list. Every tab re-applies the
     * GR/PO scope so the badges and rows agree with the deep-link.
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->icon(Heroicon::OutlinedQueueList)
                ->badge(fn (): int => $this->countFor([P2pStatus::Pending]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->where('status', P2pStatus::Pending->value)),

            'submitted' => Tab::make('Submitted')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->badge(fn (): int => $this->countFor([P2pStatus::SubmittedToAp, P2pStatus::PendingPost]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->whereIn('status', [
                    P2pStatus::SubmittedToAp->value,
                    P2pStatus::PendingPost->value,
                ])),

            'processor' => Tab::make('Processor (AP)')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->badge(fn (): int => $this->countFor([
                    P2pStatus::SubmittedToAp,
                    P2pStatus::ApReview,
                    P2pStatus::EscalatedByProcessor,
                    P2pStatus::ReturnedToApProcessor,
                ]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->whereIn('status', [
                    P2pStatus::SubmittedToAp->value,
                    P2pStatus::ApReview->value,
                    P2pStatus::EscalatedByProcessor->value,
                    P2pStatus::ReturnedToApProcessor->value,
                ])),

            'checker' => Tab::make('Checker')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->badge(fn (): int => $this->countFor([
                    P2pStatus::SubmittedToChecker,
                    P2pStatus::CheckerReview,
                    P2pStatus::Approved,
                ]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->whereIn('status', [
                    P2pStatus::SubmittedToChecker->value,
                    P2pStatus::CheckerReview->value,
                    P2pStatus::Approved->value,
                ])),

            'escalated' => Tab::make('Escalated')
                ->icon(Heroicon::OutlinedMinusCircle)
                ->badge(fn (): int => $this->countFor([
                    P2pStatus::EscalatedByVendor,
                    P2pStatus::EscalateToProcurement,
                    P2pStatus::EscalatedToWarehouse,
                    P2pStatus::PartiallyEscalated,
                ]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->whereIn('status', [
                    P2pStatus::EscalatedByVendor->value,
                    P2pStatus::EscalateToProcurement->value,
                    P2pStatus::EscalatedToWarehouse->value,
                    P2pStatus::PartiallyEscalated->value,
                ])),

            'rejected' => Tab::make('Rejected/Reversed')
                ->icon(Heroicon::OutlinedXCircle)
                ->badge(fn (): int => $this->countFor([
                    P2pStatus::Rejected,
                    P2pStatus::RejectedLegacy,
                    P2pStatus::Reversed,
                    P2pStatus::Cancelled,
                ]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->whereIn('status', [
                    P2pStatus::Rejected->value,
                    P2pStatus::RejectedLegacy->value,
                    P2pStatus::Reversed->value,
                    P2pStatus::Cancelled->value,
                ])),

            'returned' => Tab::make('Returned to Vendor')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->badge(fn (): int => $this->countFor([P2pStatus::ReturnedToVendor]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->where('status', P2pStatus::ReturnedToVendor->value)),

            'completed' => Tab::make('Completed')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->badge(fn (): int => $this->countFor([
                    P2pStatus::InvoiceParked,
                    P2pStatus::InvoicePosted,
                    P2pStatus::Completed,
                ]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)->whereIn('status', [
                    P2pStatus::InvoiceParked->value,
                    P2pStatus::InvoicePosted->value,
                    P2pStatus::Completed->value,
                ])),

            'all' => Tab::make('All')
                ->icon(Heroicon::OutlinedBanknotes)
                ->badge(fn (): int => $this->countFor([]))
                ->query(fn (Builder $query): Builder => $this->applyScopeFilter($query)),
        ];
    }

    /**
     * @param  array<int, P2pStatus>  $statuses
     */
    protected function countFor(array $statuses): int
    {
        $query = $this->applyScopeFilter(GoodsReceiptResource::getEloquentQuery());

        if ($statuses !== []) {
            $query->whereIn('status', array_map(fn (P2pStatus $status): int => $status->value, $statuses));
        }

        return $query->count();
    }

    protected function applyScopeFilter(Builder $query): Builder
    {
        return $this->applyGrOrPoFilter($query, $this->effectiveGrNumbers(), $this->effectivePurchaseOrders());
    }

    /**
     * Combined filter: match GRs linked either by purchase_order (the robust,
     * always-populated link) or by gr_numbers (backward-compatible deep links).
     * Either being present is enough to include the row.
     */
    protected function applyGrOrPoFilter(Builder $query, ?array $grNumbers, ?array $purchaseOrders): Builder
    {
        $grNumbers = array_values(array_filter($grNumbers ?? [], fn (mixed $value): bool => filled($value)));
        $purchaseOrders = array_values(array_filter($purchaseOrders ?? [], fn (mixed $value): bool => filled($value)));

        if ($grNumbers === [] && $purchaseOrders === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($grNumbers, $purchaseOrders): void {
            if ($purchaseOrders !== []) {
                $q->orWhereIn('purchase_order', $purchaseOrders);
            }

            foreach ($grNumbers as $grNumber) {
                $baseNumber = (string) preg_replace('/-P\d+$/', '', (string) $grNumber);

                $q->orWhere('good_receipt_number', $grNumber)
                    ->orWhere('good_receipt_number', $baseNumber)
                    ->orWhere('good_receipt_number', 'LIKE', $baseNumber.'-P%');
            }
        });
    }

    /**
     * @return array<int, string>|null
     */
    protected function effectiveGrNumbers(): ?array
    {
        return $this->gr_numbers ?: session('gr_numbers_filter');
    }

    /**
     * @return array<int, string>|null
     */
    protected function effectivePurchaseOrders(): ?array
    {
        return $this->purchase_orders ?: session('gr_purchase_orders_filter');
    }

    /**
     * @return array<int, string>|null
     */
    protected function normaliseFilter(mixed $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            fn (mixed $item): bool => filled($item),
        ));

        return $values === [] ? null : $values;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(PurchaseOrderResource::getUrl('index')),
        ];
    }
}
