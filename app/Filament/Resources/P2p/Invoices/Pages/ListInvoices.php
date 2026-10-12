<?php

namespace App\Filament\Resources\P2p\Invoices\Pages;

use App\Enums\P2pStatus;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('process')
                ->label('Process')
                ->icon(Heroicon::OutlinedPlay)
                ->url(fn (): string => GoodsReceiptResource::getUrl('index')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'all';
    }

    public function getTabs(): array
    {
        return [
            'in_progress' => Tab::make('In Progress')
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::SubmittedToAp->value,
                    P2pStatus::SubmittedToChecker->value,
                    P2pStatus::EscalateToProcurement->value,
                    P2pStatus::EscalatedToWarehouse->value,
                    P2pStatus::PartiallyEscalated->value,
                    P2pStatus::InvoiceParked->value,
                    P2pStatus::InvoicePosted->value,
                ])),
            'returned' => Tab::make('Returned to Vendor')
                ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::ReturnedToVendor->value,
                    P2pStatus::ReturnedToApProcessor->value,
                ])),
            'cancelled' => Tab::make('Cancelled')
                ->query(fn (Builder $query): Builder => $query->where('status', P2pStatus::Cancelled->value)),
            'all' => Tab::make('All'),
        ];
    }
}
