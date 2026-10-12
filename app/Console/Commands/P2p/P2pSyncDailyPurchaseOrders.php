<?php

namespace App\Console\Commands\P2p;

use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\Vendor;
use Illuminate\Console\Command;

/**
 * Daily PO/GR sync from SAP, one job per enabled vendor on the `daily` queue.
 * Until the SAP integration lands the bound source returns nothing, so this is a
 * safe no-op (docs/p2p/07-sap-integration.md).
 */
class P2pSyncDailyPurchaseOrders extends Command
{
    protected $signature = 'p2p:sync-daily-purchase-orders {--date= : Single day (Ymd); defaults to the last 7 days} {--vendors= : Comma-separated vendor codes}';

    protected $description = 'Dispatch the daily P2P purchase-order sync from SAP';

    public function handle(): int
    {
        $to = $this->option('date') ? now()->createFromFormat('Ymd', $this->option('date')) : now();
        $from = $this->option('date') ? $to->copy() : now()->subDays(7);

        $vendors = $this->option('vendors')
            ? explode(',', (string) $this->option('vendors'))
            : Vendor::query()->where('is_active', true)->pluck('code')->all();

        $dispatched = 0;

        foreach ($vendors as $vendor) {
            $queued = SyncP2pPurchaseOrders::dispatchUnlessPending([
                'vendor' => $vendor,
                'changed_from' => $from->format('Ymd'),
                'changed_to' => $to->format('Ymd'),
            ], queue: 'daily');

            $dispatched += $queued ? 1 : 0;
        }

        $this->info("P2P daily sync dispatched for {$dispatched} vendor(s).");

        return self::SUCCESS;
    }
}
