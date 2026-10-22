<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Actions;

use App\Enums\RoleName;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The vendor's "Sync from SAP" button: queues a PO/GR pull for their vendor code
 * (last 3 days of changes). Used on Purchase Orders and the vendor dashboard.
 */
class SyncFromSapAction
{
    public static function make(): Action
    {
        return Action::make('syncFromSap')
            ->label('Sync from SAP')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
            ->action(function (): void {
                $user = auth()->user();

                $queued = SyncP2pPurchaseOrders::dispatchUnlessPending([
                    'changed_only_history' => true,
                    'lifnr' => $user->vendor?->code,
                    'cpudt_from' => now()->subDays(3)->format('Ymd'),
                    'cpudt_to' => now()->format('Ymd'),
                ], $user->id, 'user-sync');

                Notification::make()
                    ->title($queued ? 'Sync started' : 'Sync already in progress')
                    ->{$queued ? 'success' : 'warning'}()
                    ->send();
            });
    }
}
