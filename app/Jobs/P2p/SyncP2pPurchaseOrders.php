<?php

namespace App\Jobs\P2p;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Models\P2p\SapSyncRun;
use App\Models\User;
use App\Services\P2p\P2pPurchaseOrderSyncService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pulls changed POs/GRs from SAP into the mirror tables.
 *
 * A run is deduplicated with a cache key: one key per vendor for targeted
 * syncs, and one shared `all` key for the daily bulk run. The SAP read itself is
 * behind {@see SapPurchaseOrderSource}; with SAP disabled the bound source is a
 * no-op.
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

        // One shared key for the daily all-vendor run, even when its date window
        // changes between attempts.
        return 'p2p-po-sync:pending:'.($vendor !== '' ? $vendor : 'all');
    }

    /**
     * Queue a sync unless one is already pending for the same scope.
     */
    public static function dispatchUnlessPending(
        array $filters = [],
        ?int $notifyUserId = null,
        string $queue = 'default',
        bool $sync = false,
    ): bool {
        if (! Cache::add(self::pendingKey($filters), true, 1800)) {
            return false;
        }

        if ($sync) {
            static::dispatchSync($filters, $notifyUserId);

            return true;
        }

        static::dispatch($filters, $notifyUserId)->onQueue($queue);

        return true;
    }

    public function handle(SapPurchaseOrderSource $source, P2pPurchaseOrderSyncService $sync): void
    {
        $run = SapSyncRun::query()->create([
            'vendor_code' => $this->vendorCode(),
            'window_from' => $this->dateFilter(['cpudt_from', 'bedat_from']),
            'window_to' => $this->dateFilter(['cpudt_to', 'bedat_to']),
            'filters' => $this->filters,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $sync->run($source, $this->filters);

            $run->update([
                'status' => 'success',
                'discovered' => $result['discovered'],
                'synced' => $result['synced'],
                'skipped_unknown_vendor' => $result['skipped_unknown_vendor'],
                'skipped_unknown_company' => $result['skipped_unknown_company'],
                'created_vendors' => $result['created_vendors'],
                'reversals' => $result['reversals'],
                'errors' => $result['errors'],
                'finished_at' => now(),
            ]);

            $this->notify('SAP sync complete', $this->summary($result));
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        } finally {
            Cache::forget(self::pendingKey($this->filters));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::pendingKey($this->filters));
        $this->notify('SAP sync failed', $exception?->getMessage() ?? 'Unknown error.', success: false);
    }

    /**
     * @param  array<string, int>  $result
     */
    protected function summary(array $result): string
    {
        return sprintf(
            'Discovered %d, synced %d, skipped %d unknown vendor(s) and %d unknown company(ies), %d reversal(s), %d error(s).',
            $result['discovered'],
            $result['synced'],
            $result['skipped_unknown_vendor'],
            $result['skipped_unknown_company'],
            $result['reversals'],
            $result['errors'],
        );
    }

    protected function notify(string $title, ?string $body = null, bool $success = true): void
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
            ->body($body)
            ->{$success ? 'success' : 'danger'}()
            ->sendToDatabase($user);
    }

    protected function vendorCode(): ?string
    {
        $vendor = trim((string) ($this->filters['lifnr'] ?? $this->filters['vendor'] ?? ''));

        return $vendor !== '' ? $vendor : null;
    }

    /**
     * @param  list<string>  $keys
     */
    protected function dateFilter(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->filters[$key] ?? null;

            if (filled($value)) {
                return Carbon::parse((string) $value)->toDateString();
            }
        }

        return null;
    }
}
