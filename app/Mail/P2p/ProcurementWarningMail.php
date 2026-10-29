<?php

namespace App\Mail\P2p;

use App\Models\P2p\Escalation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Overdue-warning mail sent to Points of Contact. Queued, and guarded so it is not
 * re-sent daily (docs/p2p/06-workflow-statuses.md §6).
 */
class ProcurementWarningMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Escalation $escalation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[P2P] Overdue escalation warning');
    }

    public function content(): Content
    {
        $this->escalation->loadMissing(['reason', 'invoice.vendor', 'invoice.company']);

        return new Content(
            view: 'mail.p2p.procurement-warning',
            with: [
                'escalation' => $this->escalation,
                'shareUrl' => $this->shareUrl(),
            ],
        );
    }

    protected function shareUrl(): ?string
    {
        if ($this->escalation->p2p_invoice_id === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            'filament.hub.pages.p2p.bulk-goods-receipt',
            now()->addDays(7),
            ['invoice' => $this->escalation->p2p_invoice_id],
        );
    }
}
