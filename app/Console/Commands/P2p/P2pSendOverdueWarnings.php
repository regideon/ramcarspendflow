<?php

namespace App\Console\Commands\P2p;

use App\Mail\P2p\ProcurementWarningMail;
use App\Models\P2p\EmailNotificationReceiver;
use App\Models\P2p\Escalation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Warns Points of Contact about escalations open for 3+ days. Guarded by
 * `invoices.warning_sent_at` so it does not repeat every day like the legacy job.
 */
class P2pSendOverdueWarnings extends Command
{
    protected $signature = 'p2p:send-overdue-warnings {--days=3 : Age threshold in days}';

    protected $description = 'Email Points of Contact about overdue P2P escalations';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $escalations = Escalation::query()
            ->where('status', 'open')
            ->where('escalated_at', '<=', $cutoff)
            ->whereHas('invoice', fn ($query) => $query->whereNull('warning_sent_at'))
            ->with('invoice')
            ->get();

        $recipients = EmailNotificationReceiver::query()->active()->pluck('email')->filter();

        foreach ($escalations as $escalation) {
            foreach ($recipients as $email) {
                Mail::to($email)->queue(new ProcurementWarningMail($escalation));
            }

            $escalation->invoice?->forceFill(['warning_sent_at' => now()])->save();
        }

        $this->info("Sent overdue warnings for {$escalations->count()} escalation(s).");

        return self::SUCCESS;
    }
}
