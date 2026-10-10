<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Mail\P2p\GoodReceiptCancelledMail;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Returns and cancellations for P2P invoices:
 * - AP Processor / Procurement → Return to Vendor
 * - AP Supervisor (checker)     → Return to AP Processor
 * - Vendor                      → Cancel a returned request
 *
 * Each transition runs in a transaction with a row lock, requires remarks, writes a
 * history row and recomputes the GR states and PO status.
 */
class P2pGoodReceiptReturnService
{
    public function __construct(
        private readonly P2pGoodReceiptStatusService $goodReceiptStatus,
        private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus,
    ) {}

    public function returnToVendor(Invoice $invoice, User $by, string $remarks): Invoice
    {
        return $this->transition($invoice, P2pStatus::ReturnedToVendor, $by, $remarks, 'Returned to vendor.', true);
    }

    public function returnToApProcessor(Invoice $invoice, User $by, string $remarks): Invoice
    {
        return $this->transition($invoice, P2pStatus::ReturnedToApProcessor, $by, $remarks, 'Returned to AP Processor.', false);
    }

    /**
     * Vendor cancels a returned request. The GRs go back to being billable.
     */
    public function cancel(Invoice $invoice, User $by, string $remarks): Invoice
    {
        if ($invoice->status !== P2pStatus::ReturnedToVendor) {
            throw ValidationException::withMessages(['status' => 'Only a returned invoice can be cancelled.']);
        }

        $invoice = $this->transition($invoice, P2pStatus::Cancelled, $by, $remarks, 'Request cancelled by vendor.', true);

        if ($invoice->vendor?->email !== null) {
            Mail::to($invoice->vendor->email)->queue(new GoodReceiptCancelledMail($invoice));
        }

        return $invoice;
    }

    protected function transition(
        Invoice $invoice,
        P2pStatus $to,
        User $by,
        string $remarks,
        string $historyRemarks,
        bool $visibleToVendor,
    ): Invoice {
        if (trim($remarks) === '') {
            throw ValidationException::withMessages(['remarks' => 'Remarks are required.']);
        }

        return DB::transaction(function () use ($invoice, $to, $by, $remarks, $historyRemarks, $visibleToVendor): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $from = $invoice->status;

            $invoice->forceFill(['status' => $to])->save();

            $this->goodReceiptStatus->recalculateForInvoice($invoice);

            SapPurchaseOrder::query()
                ->whereIn('purchase_order', $invoice->po_numbers ?? [])
                ->get()
                ->each(fn (SapPurchaseOrder $po) => $this->purchaseOrderStatus->recalculate($po));

            ApprovalHistory::query()->create([
                'p2p_invoice_id' => $invoice->id,
                'actioned_by' => $by->id,
                'from_status' => $from,
                'to_status' => $to,
                'remarks' => $remarks ?: $historyRemarks,
                'is_visible_to_vendor' => $visibleToVendor,
                'is_visible_to_admin' => true,
            ]);

            return $invoice->fresh();
        });
    }
}
