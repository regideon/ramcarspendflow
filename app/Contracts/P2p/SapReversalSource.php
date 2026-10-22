<?php

namespace App\Contracts\P2p;

/**
 * Optional capability for a SAP source that can report GR reversals.
 *
 * Kept separate from {@see SapPurchaseOrderSource} so test fakes and the null
 * source do not need to implement it.
 */
interface SapReversalSource
{
    /**
     * Fully reversed goods receipts for a purchase order.
     *
     * @return array{
     *     reversed: array<string, string>,
     *     replacements: array<string, string>
     * }
     */
    public function reversalsForPurchaseOrder(string $purchaseOrder): array;
}
