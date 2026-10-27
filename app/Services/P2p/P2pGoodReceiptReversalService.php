<?php

namespace App\Services\P2p;

use App\Models\P2p\Escalation;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Support\GoodReceiptNumber;
use Illuminate\Support\Facades\DB;

/**
 * Applies a SAP goods-receipt reversal (document type 102): marks the GR reversed,
 * links the replacement GR the vendor must act on instead, and auto-resolves any
 * open escalation on it. See docs/p2p/06-workflow-statuses.md §6.
 */
class P2pGoodReceiptReversalService
{
    public function __construct(
        private readonly P2pGoodReceiptStatusService $goodReceiptStatus,
        private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus,
        private readonly P2pEscalationRoutingService $escalations,
    ) {}

    public function apply(
        GoodReceiptState $state,
        ?string $reversalDocument,
        ?string $replacementGrNumber = null,
        bool $cancelEscalations = true,
    ): void {
        DB::transaction(function () use ($state, $reversalDocument, $replacementGrNumber, $cancelEscalations): void {
            /** @var GoodReceiptState $state */
            $state = GoodReceiptState::query()->whereKey($state->getKey())->lockForUpdate()->firstOrFail();

            $state->forceFill([
                'reversed_at' => now(),
                'reversal_document' => $reversalDocument,
                'replacement_gr_number' => $replacementGrNumber,
            ])->save();

            // The derived status now reports Reversed (reversed_at wins).
            $this->goodReceiptStatus->recalculate($state);

            if ($cancelEscalations) {
                Escalation::query()
                    ->where('p2p_good_receipt_state_id', $state->id)
                    ->where('status', 'open')
                    ->get()
                    ->each(fn (Escalation $escalation) => $this->escalations->cancel($escalation, null, 'GR reversed in SAP.'));
            }

            SapPurchaseOrder::query()
                ->where('purchase_order', $state->purchase_order)
                ->get()
                ->each(fn (SapPurchaseOrder $po) => $this->purchaseOrderStatus->recalculate($po));
        });
    }

    /**
     * Move the request from the reversed GR (and its splits) onto the SAP
     * replacement re-GR(s).
     *
     * @param  array<int, string>  $replacementGrNumbers
     */
    public function substituteReplacementGrs(Invoice $invoice, string $reversedGrNumber, array $replacementGrNumbers): void
    {
        $base = GoodReceiptNumber::base($reversedGrNumber);
        $replacements = collect($replacementGrNumbers)
            ->filter(fn (mixed $number): bool => filled($number))
            ->map(fn (mixed $number): string => (string) $number)
            ->unique()
            ->values();

        if ($replacements->isEmpty()) {
            return;
        }

        $numbers = collect($invoice->gr_numbers ?? [])
            ->reject(fn (mixed $number): bool => GoodReceiptNumber::base(is_scalar($number) ? (string) $number : null) === $base)
            ->merge($replacements)
            ->unique()
            ->values()
            ->all();

        $invoice->forceFill(['gr_numbers' => $numbers])->save();
    }
}
