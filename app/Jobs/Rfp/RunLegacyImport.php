<?php

namespace App\Jobs\Rfp;

use App\Models\Rfp\LegacyImportRun;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Runs `rfp:import-legacy` for the Settings page (dispatched after the response, so it
 * needs no queue worker) and records the outcome on the run.
 */
class RunLegacyImport
{
    use Dispatchable;

    public function __construct(public int $runId, public string $userEmail) {}

    public function handle(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $run = LegacyImportRun::findOrFail($this->runId);

        try {
            $exitCode = Artisan::call('rfp:import-legacy', ['--force' => true]);
            $status = $exitCode === 0 ? LegacyImportRun::SUCCEEDED : LegacyImportRun::FAILED;
            $output = Artisan::output();
        } catch (Throwable $exception) {
            report($exception);
            $status = LegacyImportRun::FAILED;
            $output = $exception->getMessage();
        }

        $run->update(['status' => $status, 'output' => $output, 'finished_at' => now()]);

        // Look the user up after the import: it may have rewritten their row.
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($this->userEmail)])->first();

        if ($user) {
            Notification::make()
                ->title($status === LegacyImportRun::SUCCEEDED ? 'Legacy RFP import finished' : 'Legacy RFP import failed')
                ->body('See RFP Settings → Legacy Import for the details.')
                ->status($status === LegacyImportRun::SUCCEEDED ? 'success' : 'danger')
                ->sendToDatabase($user);
        }
    }
}
