<?php

namespace App\Console\Commands\P2p;

use App\Models\P2p\SapPurchaseOrder;
use App\Services\P2p\P2pPurchaseOrderStatusService;
use Illuminate\Console\Command;

/**
 * Recomputes every PO status from its GR states. Dry-run by default; pass --write to
 * persist. Useful after a sync or a bulk data fix.
 */
class P2pBackfillStatuses extends Command
{
    protected $signature = 'p2p:backfill-statuses {--write : Persist the changes} {--po= : Limit to one purchase order}';

    protected $description = 'Recompute P2P purchase-order statuses from GR states';

    public function handle(P2pPurchaseOrderStatusService $status): int
    {
        $query = SapPurchaseOrder::query();

        if ($po = $this->option('po')) {
            $query->where('purchase_order', $po);
        }

        $write = (bool) $this->option('write');
        $changed = 0;

        $query->chunkById(200, function ($orders) use ($status, $write, &$changed): void {
            foreach ($orders as $order) {
                $derived = $status->derive($order);

                if ($order->status_id !== $derived) {
                    $changed++;

                    if ($write) {
                        $order->forceFill(['status_id' => $derived])->save();
                    }
                }
            }
        });

        $this->info($write
            ? "Updated {$changed} purchase order status(es)."
            : "{$changed} purchase order status(es) would change (dry run; pass --write).");

        return self::SUCCESS;
    }
}
