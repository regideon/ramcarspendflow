<?php

namespace App\Filament\Resources\P2p\GoodsReceipts\Pages;

use App\Enums\P2pStatus;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    /**
     * Status tabs mirroring the legacy Goods Receipts list.
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->badge(fn (): int => $this->countFor([P2pStatus::Pending]))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [P2pStatus::Pending->value])),
            'submitted_ap' => Tab::make('Submitted to AP')
                ->badge(fn (): int => $this->countFor([P2pStatus::SubmittedToAp]))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [P2pStatus::SubmittedToAp->value])),
            'escalated' => Tab::make('Escalate to Procurement')
                ->badge(fn (): int => $this->countFor([P2pStatus::EscalateToProcurement, P2pStatus::EscalatedToWarehouse, P2pStatus::PartiallyEscalated]))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::EscalateToProcurement->value,
                    P2pStatus::EscalatedToWarehouse->value,
                    P2pStatus::PartiallyEscalated->value,
                ])),
            'rejected' => Tab::make('Rejected/Reversed')
                ->badge(fn (): int => $this->countFor([P2pStatus::Rejected, P2pStatus::RejectedLegacy, P2pStatus::Reversed]))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::Rejected->value,
                    P2pStatus::RejectedLegacy->value,
                    P2pStatus::Reversed->value,
                ])),
            'returned' => Tab::make('Returned to Vendor')
                ->badge(fn (): int => $this->countFor([P2pStatus::ReturnedToVendor]))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [P2pStatus::ReturnedToVendor->value])),
            'posted' => Tab::make('Posted to SAP')
                ->badge(fn (): int => $this->countFor([P2pStatus::InvoiceParked, P2pStatus::InvoicePosted, P2pStatus::Completed]))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::InvoiceParked->value,
                    P2pStatus::InvoicePosted->value,
                    P2pStatus::Completed->value,
                ])),
            'all' => Tab::make('All')
                ->badge(fn (): int => $this->countFor([])),
        ];
    }

    /**
     * @param  array<int, P2pStatus>  $statuses
     */
    protected function countFor(array $statuses): int
    {
        $query = GoodsReceiptResource::getEloquentQuery();

        if ($statuses !== []) {
            $query->whereIn('status', array_map(fn (P2pStatus $status): int => $status->value, $statuses));
        }

        return $query->count();
    }
}
