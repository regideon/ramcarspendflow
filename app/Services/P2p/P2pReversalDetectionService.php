<?php

namespace App\Services\P2p;

use App\Models\P2p\GoodReceiptState;

/**
 * Detects reversed goods receipts from a sync (SAP EKBE, BWART 102) and applies the
 * reversal + replacement link. The sync supplies the detected GR numbers; this keeps
 * the detection rule separate from the SAP transport (docs/p2p/07-sap-integration.md).
 */
class P2pReversalDetectionService
{
    public function __construct(private readonly P2pGoodReceiptReversalService $reversals) {}

    /**
     * @param  array<string, string>  $reversed  GR number => reversal document
     * @param  array<string, string>  $replacements  GR number => replacement GR number
     * @return int number of reversals applied
     */
    public function detect(array $reversed, array $replacements = []): int
    {
        $applied = 0;

        foreach ($reversed as $grNumber => $reversalDocument) {
            $state = GoodReceiptState::query()
                ->where('good_receipt_number', $grNumber)
                ->whereNull('reversed_at')
                ->first();

            if ($state === null) {
                continue;
            }

            $this->reversals->apply($state, $reversalDocument, $replacements[$grNumber] ?? null);
            $applied++;
        }

        return $applied;
    }
}
