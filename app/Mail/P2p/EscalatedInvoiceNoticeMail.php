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
 * Notice to the originator that their escalation was received and forwarded.
 */
class EscalatedInvoiceNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Escalation $escalation) {}

    public function envelope(): Envelope
    {
        $reason = $this->escalation->reason?->name ?? 'Escalation';

        return new Envelope(subject: "[P2P Escalation] {$reason} — Received");
    }

    public function content(): Content
    {
        $this->escalation->loadMissing(['reason', 'invoice.vendor', 'invoice.company', 'goodReceiptState']);

        return new Content(
            view: 'mail.p2p.escalation-notification',
            with: [
                'escalation' => $this->escalation,
                'audience' => 'origin',
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
