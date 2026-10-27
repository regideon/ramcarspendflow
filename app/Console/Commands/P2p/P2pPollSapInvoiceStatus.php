<?php

namespace App\Console\Commands\P2p;

use App\Services\P2p\P2pInvoiceStatusPollService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('p2p:poll-sap-invoice-status {--limit= : Maximum invoices per run}')]
#[Description('Advance parked P2P invoices through ACDOCA (parked → posted → cleared)')]
class P2pPollSapInvoiceStatus extends Command
{
    public function handle(): int
    {
        if (! config('p2p.sap.enabled')) {
            $this->info('SAP integration is disabled (SAP_ENABLED=false).');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('p2p.sap.acdoca_poll_limit', 50));

        $counts = app(P2pInvoiceStatusPollService::class)->poll($limit);

        $this->table(
            ['Checked', 'Parked', 'Posted', 'Completed', 'Skipped', 'Failed'],
            [[
                $counts['checked'],
                $counts['parked'],
                $counts['posted'],
                $counts['completed'],
                $counts['skipped'],
                $counts['failed'],
            ]],
        );

        return self::SUCCESS;
    }
}
