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
 * Sent to the handlers of the current escalation hop. Queued.
 */
class EscalateToProcurementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Escalation $escalation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[P2P Escalation] Action Required');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.p2p.escalation',
            with: [
                'heading' => 'Escalation action required',
                'body' => "An invoice has been escalated and is waiting on {$this->escalation->current_role}.",
                'escalation' => $this->escalation,
            ],
        );
    }
}
