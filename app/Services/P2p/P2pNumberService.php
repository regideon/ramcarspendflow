<?php

namespace App\Services\P2p;

use App\Models\P2p\Invoice;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Generates the human-readable P2P request number, e.g. `P2P-2026-00000042`.
 *
 * The number is derived from the invoice's GRC number (both share the same
 * sequence), so a request can be traced back to its GRC. The sequence is a
 * minimum of eight digits: it keeps growing past eight digits instead of
 * wrapping, which would collide with an existing number.
 */
class P2pNumberService
{
    public function forInvoice(Invoice $invoice): string
    {
        return $this->generate(
            $this->sequenceFor($invoice),
            $invoice->created_at ?? now(),
        );
    }

    public function generate(int $sequence, DateTimeInterface $createdAt): string
    {
        return sprintf('P2P-%s-%08d', $createdAt->format('Y'), $sequence);
    }

    /**
     * The numeric part of the GRC number, or the invoice id when it has none yet.
     */
    public function sequenceFor(Invoice $invoice): int
    {
        if (filled($invoice->grc_number)) {
            return (int) preg_replace('/\D+/', '', $invoice->grc_number);
        }

        return (int) $invoice->getKey();
    }

    /**
     * Format a database row during the backfill migration, where no model exists.
     */
    public function forStoredInvoice(int $sequence, CarbonInterface|string $createdAt): string
    {
        return $this->generate($sequence, $createdAt instanceof CarbonInterface ? $createdAt : Carbon::parse($createdAt));
    }
}
