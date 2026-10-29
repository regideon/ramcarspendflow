<?php

namespace App\Console\Commands\P2p;

use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\P2p\SapSyncRun;
use Illuminate\Console\Command;

/**
 * Fetch a single SAP purchase order directly, bypassing the vendor/date
 * discovery window. Intended for support and for testing one PO safely.
 */
class P2pSyncPurchaseOrder extends Command
{
    protected $signature = 'p2p:sync-purchase-order
        {po : SAP purchase order number}
        {--create-vendor : Create an inactive stub vendor if the SAP vendor is missing}
        {--queue : Dispatch to the queue instead of running inline}';

    protected $description = 'Sync one P2P purchase order and its goods receipts from SAP';

    public function handle(): int
    {
        $purchaseOrder = trim((string) $this->argument('po'));

        if ($purchaseOrder === '') {
            $this->error('A purchase order number is required.');

            return self::FAILURE;
        }

        if ($this->option('create-vendor')) {
            config(['p2p.sap.unknown_vendors' => 'create']);
        }

        $filters = ['po_numbers' => [$purchaseOrder]];

        if ($this->option('queue')) {
            $queued = SyncP2pPurchaseOrders::dispatchUnlessPending($filters);

            $this->info($queued
                ? 'P2P purchase-order sync dispatched.'
                : 'A sync for this purchase order is already queued or running.');

            return self::SUCCESS;
        }

        SyncP2pPurchaseOrders::dispatchSync($filters);

        $run = SapSyncRun::query()->latest('id')->first();

        if ($run === null) {
            $this->error('The SAP sync did not record a run.');

            return self::FAILURE;
        }

        $this->table(
            ['Status', 'Discovered', 'Synced', 'Unknown vendor', 'Unknown company', 'Created vendors', 'Reversals', 'Errors'],
            [[
                $run->status,
                $run->discovered,
                $run->synced,
                $run->skipped_unknown_vendor,
                $run->skipped_unknown_company,
                $run->created_vendors,
                $run->reversals ?? 0,
                $run->errors,
            ]],
        );

        if ($run->status === 'failed') {
            $this->error($run->error_message ?? 'SAP sync failed.');

            return self::FAILURE;
        }

        if ($run->synced === 0 && $run->skipped_unknown_vendor > 0) {
            $this->warn('The PO was found but its SAP vendor is not in the portal. Re-run with --create-vendor to create an inactive stub.');
        }

        return self::SUCCESS;
    }
}
