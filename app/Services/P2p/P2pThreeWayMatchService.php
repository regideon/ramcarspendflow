<?php

namespace App\Services\P2p;

use App\Enums\P2pThreeWayMatchStatus;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;

/**
 * PO vs GR vs invoice reconciliation for a line, shown as a badge in the UI.
 */
class P2pThreeWayMatchService
{
    /**
     * Compare the billed quantity to what was received and ordered.
     */
    public function match(SapGoodReceiptItem $item, InvoiceLine $line): P2pThreeWayMatchStatus
    {
        $ordered = (string) ($item->quantity_po ?? '0');
        $received = (string) ($item->quantity_served ?? $item->quantity_po ?? '0');
        $billed = (string) $line->quantity_billed;

        // Invoiced more than received — a real mismatch (over-delivery, wrong qty).
        if (bccomp($billed, $received, 3) > 0) {
            return P2pThreeWayMatchStatus::NoMatch;
        }

        // Invoice reconciles with the receipt, but the PO still has an open quantity.
        if (bccomp($received, $ordered, 3) < 0) {
            return P2pThreeWayMatchStatus::OpenPo;
        }

        return P2pThreeWayMatchStatus::Matched;
    }

    /**
     * Match a line to its mirror item, if linked.
     */
    public function matchLine(InvoiceLine $line): ?P2pThreeWayMatchStatus
    {
        $item = $line->sapItem;

        return $item === null ? null : $this->match($item, $line);
    }
}
