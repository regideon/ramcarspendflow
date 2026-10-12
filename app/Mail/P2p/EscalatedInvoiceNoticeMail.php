<?php

namespace App\Mail\P2p;

use App\Models\P2p\Escalation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notice to the vendor that their escalation was received. Queued.
 */
class EscalatedInvoiceNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Escalation $escalation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[P2P] Escalation received');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.p2p.escalation',
            with: [
                'heading' => 'Escalation received',
                'body' => 'We received your escalation and routed it for action.',
                'escalation' => $this->escalation,
            ],
        );
    }
}
