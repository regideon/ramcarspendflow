<?php

namespace App\Console\Commands\P2p;

use App\Jobs\P2p\SyncP2pPurchaseOrders;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily PO/GR sync from SAP.
 *
 * The default is ONE all-vendor run: the legacy per-vendor fan-out created one
 * job per active vendor (7,000+ jobs here) and made the daily sync look broken
 * when only a few jobs were processed. Use `--vendors=` for a targeted run or
 * `--sync` to run inline while testing.
 */
class P2pSyncDailyPurchaseOrders extends Command
{
    protected $signature = 'p2p:sync-daily-purchase-orders
        {--vendors= : Comma-separated vendor codes for a targeted sync}
        {--date= : Single day (Ymd); defaults to the last 7 days}
        {--from= : Start date (Ymd)}
        {--to= : End date (Ymd)}
        {--months= : Look back N months instead of the default 7 days}
        {--sync : Run inline instead of queueing}';

    protected $description = 'Sync P2P purchase orders and goods receipts from SAP';

    public function handle(): int
    {
        [$from, $to] = $this->window();

        $filters = [
            'changed_only_history' => true,
            'cpudt_from' => $from->format('Ymd'),
            'cpudt_to' => $to->format('Ymd'),
        ];

        $vendors = $this->option('vendors')
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('vendors')))))
            : [];

        if ($vendors !== []) {
            $dispatched = 0;

            foreach ($vendors as $vendor) {
                $queued = SyncP2pPurchaseOrders::dispatchUnlessPending([
                    ...$filters,
                    'lifnr' => $vendor,
                ], queue: 'daily', sync: (bool) $this->option('sync'));

                $dispatched += $queued ? 1 : 0;
            }

            $verb = $this->option('sync') ? 'completed' : 'dispatched';
            $this->info("P2P sync {$verb} for {$dispatched} of ".count($vendors).' vendor(s).');

            return self::SUCCESS;
        }

        $queued = SyncP2pPurchaseOrders::dispatchUnlessPending(
            $filters,
            queue: 'daily',
            sync: (bool) $this->option('sync'),
        );

        $this->info(match (true) {
            ! $queued => 'A P2P all-vendor sync is already queued or running.',
            (bool) $this->option('sync') => 'P2P all-vendor sync completed.',
            default => 'P2P all-vendor sync dispatched.',
        });

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function window(): array
    {
        if ($this->option('date')) {
            $day = Carbon::createFromFormat('Ymd', (string) $this->option('date'))->startOfDay();

            return [$day->copy(), $day->copy()];
        }

        $to = $this->option('to')
            ? Carbon::createFromFormat('Ymd', (string) $this->option('to'))->endOfDay()
            : now();

        if ($this->option('from')) {
            $from = Carbon::createFromFormat('Ymd', (string) $this->option('from'))->startOfDay();
        } elseif ($this->option('months')) {
            $from = now()->subMonths(max(1, (int) $this->option('months')))->startOfDay();
        } else {
            $from = now()->subDays(7)->startOfDay();
        }

        return [$from, $to];
    }
}
