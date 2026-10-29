<?php

namespace App\Console\Commands\P2p;

use App\Models\P2p\SapApiLog;
use App\Services\P2p\Sap\P2pSapApiLogsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('p2p:sap-logs:prune {--days=90 : Retention window in days} {--type= : Only soap or odata}')]
#[Description('Prune P2P SAP communication logs (default retention: 90 days)')]
class P2pPruneSapLogs extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $type = $this->option('type');

        if ($type !== null && ! in_array($type, [SapApiLog::TYPE_SOAP, SapApiLog::TYPE_ODATA], true)) {
            $this->error('Unknown SAP log type. Use soap or odata.');

            return self::FAILURE;
        }

        $deleted = app(P2pSapApiLogsService::class)->cleanupOldLogs($days, $type);

        $this->info("Deleted {$deleted} SAP log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
