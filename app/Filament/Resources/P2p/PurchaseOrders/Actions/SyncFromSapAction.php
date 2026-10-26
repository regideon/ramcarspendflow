<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Actions;

use App\Enums\RoleName;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The vendor's "Sync from SAP" button: queues a PO/GR pull for every vendor code
 * assigned to the login (last 3 days of changes). Used on Purchase Orders and the
 * vendor dashboard.
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

                if (! $user instanceof User) {
                    return;
                }

                $vendorCodes = $user->vendorCodes();
                $queued = self::dispatchFor($user);

                $title = match (true) {
                    $vendorCodes === [] => 'No vendor code assigned',
                    $queued === 0 => 'Sync already in progress',
                    default => 'Sync started',
                };

                $body = match (true) {
                    $vendorCodes === [] => 'Assign at least one vendor to this login before syncing.',
                    $queued === 0 => 'A sync is already queued or running for your vendor code(s).',
                    default => 'Queued '.$queued.' of '.count($vendorCodes).' vendor code(s).',
                };

                Notification::make()
                    ->title($title)
                    ->body($body)
                    ->{$queued > 0 ? 'success' : 'warning'}()
                    ->send();
            });
    }

    /**
     * Queue one sync per vendor code assigned to the user.
     *
     * @return int number of jobs queued (0 when every vendor is already pending
     *             or the user has no vendor assignments)
     */
    public static function dispatchFor(User $user): int
    {
        $queued = 0;

        foreach ($user->vendorCodes() as $vendorCode) {
            $queued += SyncP2pPurchaseOrders::dispatchUnlessPending([
                'changed_only_history' => true,
                'lifnr' => $vendorCode,
                'cpudt_from' => now()->subDays(3)->format('Ymd'),
                'cpudt_to' => now()->format('Ymd'),
            ], $user->id, 'user-sync') ? 1 : 0;
        }

        return $queued;
    }
}
