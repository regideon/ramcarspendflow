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
 * Sent to the handlers of the current escalation hop. Queued.
 */
class EscalateToProcurementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Escalation $escalation) {}

    public function envelope(): Envelope
    {
        $reason = $this->escalation->reason?->name ?? 'Escalation';

        return new Envelope(subject: "[P2P Escalation] {$reason} — Action Required");
    }

    public function content(): Content
    {
        $this->escalation->loadMissing(['reason', 'invoice.vendor', 'invoice.company', 'goodReceiptState']);

        return new Content(
            view: 'mail.p2p.escalation-notification',
            with: [
                'escalation' => $this->escalation,
                'audience' => 'hop',
                'teamLabel' => Escalation::roleLabel($this->escalation->current_role),
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
