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
        return new Content(
            view: 'mail.p2p.escalation',
            with: [
                'heading' => 'Overdue escalation',
                'body' => 'This escalation has been open for more than three days.',
                'escalation' => $this->escalation,
            ],
        );
    }
}
