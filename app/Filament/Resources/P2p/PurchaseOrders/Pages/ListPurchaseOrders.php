<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromSap')
                ->label('Sync from SAP')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
                ->action(function (): void {
                    $user = auth()->user();

                    $queued = SyncP2pPurchaseOrders::dispatchUnlessPending([
                        'vendor' => $user->vendor?->code,
                        'changed_from' => now()->subDays(3)->format('Ymd'),
                        'changed_to' => now()->format('Ymd'),
                    ], $user->id, 'user-sync');

                    Notification::make()
                        ->title($queued ? 'Sync started' : 'Sync already in progress')
                        ->{$queued ? 'success' : 'warning'}()
                        ->send();
                }),
        ];
    }
}
