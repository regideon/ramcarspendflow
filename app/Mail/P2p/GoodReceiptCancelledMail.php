<?php

namespace App\Mail\P2p;

use App\Models\P2p\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notice to the vendor that a request was cancelled. Queued.
 */
class GoodReceiptCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[P2P] Request cancelled');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.p2p.escalation',
            with: [
                'heading' => 'Request cancelled',
                'body' => "Invoice {$this->invoice->invoice_number} was cancelled.",
                'escalation' => null,
            ],
        );
    }
}
