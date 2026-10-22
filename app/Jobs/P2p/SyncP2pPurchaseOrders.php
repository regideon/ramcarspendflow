<?php

namespace App\Jobs\P2p;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Models\User;
use App\Services\P2p\P2pPurchaseOrderSyncService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pulls changed POs/GRs from SAP into the mirror tables. One queued run per vendor,
 * deduplicated with an atomic cache lock so repeated clicks or overlapping schedules
 * produce a single job.
 *
 * The SAP read itself is behind {@see SapPurchaseOrderSource}; until the integration
 * lands the bound source returns nothing, so this is a safe no-op.
 */
class SyncP2pPurchaseOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public array $filters = [],
        public ?int $notifyUserId = null,
    ) {}

    public static function pendingKey(array $filters): string
    {
        $vendor = trim((string) ($filters['lifnr'] ?? $filters['vendor'] ?? ''));

        return 'p2p-po-sync:pending:'.($vendor !== '' ? $vendor : md5(json_encode($filters)));
    }

    /**
     * Queue a sync unless one is already pending for the same vendor.
     */
    public static function dispatchUnlessPending(array $filters = [], ?int $notifyUserId = null, string $queue = 'default'): bool
    {
        if (! Cache::add(self::pendingKey($filters), true, 1800)) {
            return false;
        }

        static::dispatch($filters, $notifyUserId)->onQueue($queue);

        return true;
    }

    public function handle(SapPurchaseOrderSource $source, P2pPurchaseOrderSyncService $sync): void
    {
        try {
            $count = $sync->run($source, $this->filters);
            $this->notify("SAP sync complete ({$count} purchase orders).");
        } finally {
            Cache::forget(self::pendingKey($this->filters));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::pendingKey($this->filters));
        $this->notify('SAP sync failed.', success: false);
    }

    protected function notify(string $title, bool $success = true): void
    {
        if ($this->notifyUserId === null) {
            return;
        }

        $user = User::query()->find($this->notifyUserId);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title($title)
            ->{$success ? 'success' : 'danger'}()
            ->sendToDatabase($user);
    }
}
