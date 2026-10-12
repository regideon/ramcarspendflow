<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Invoice;
use App\Models\User;
use App\Services\P2p\Sap\P2pSapApiLogsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parks an approved invoice in SAP (the AP Supervisor's "Approve" step).
 *
 * **The SAP write side is not built yet** (docs/p2p/07-sap-integration.md). This
 * records the supervisor's decision, logs the payload that will be sent, and moves
 * the invoice to `PendingPost` — the ACDOCA poll later confirms the parked document
 * number (`BELNR`) and advances it to Posted/Completed. When the AL11/SOAP
 * interface lands, the parked call replaces the log call here.
 */
class P2pInvoiceParkingService
{
    public function __construct(
        private readonly P2pSapApiLogsService $sapLogs,
    ) {}

    public function park(Invoice $invoice, User $by, string $remarks): Invoice
    {
        if (trim($remarks) === '') {
            throw ValidationException::withMessages(['remarks' => 'Remarks are required.']);
        }

        if ($invoice->status !== P2pStatus::SubmittedToChecker) {
            throw ValidationException::withMessages([
                'status' => 'Only a submission awaiting the checker can be approved.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $by, $remarks): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $from = $invoice->status;

            $invoice->forceFill([
                'status' => P2pStatus::PendingPost,
                'processed' => true,
            ])->save();

            $this->sapLogs->log(
                $invoice,
                'park_invoice',
                json_encode($this->payloadFor($invoice), JSON_THROW_ON_ERROR),
                'pending',
            );

            ApprovalHistory::query()->create([
                'p2p_invoice_id' => $invoice->id,
                'actioned_by' => $by->id,
                'from_status' => $from,
                'to_status' => P2pStatus::PendingPost,
                'remarks' => $remarks,
                'is_visible_to_vendor' => false,
                'is_visible_to_admin' => true,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * The header SAP will receive. Mirrors the legacy payload shape so the eventual
     * integration is a swap, not a redesign.
     *
     * @return array<string, mixed>
     */
    protected function payloadFor(Invoice $invoice): array
    {
        return [
            'ACTION' => 'PARK',
            'REF_DOC_NO' => implode(', ', array_unique($invoice->reference_number ?? [])),
            'DOC_DATE' => now()->format('Y-m-d'),
            'GROSS_AMOUNT' => (string) $invoice->total,
            'TAX_CODE' => $invoice->tax_code,
            'WI_TAX_CODE' => $invoice->withholding_tax_code,
            'PO_NUMBERS' => $invoice->po_numbers ?? [],
            'GR_NUMBERS' => $invoice->gr_numbers ?? [],
        ];
    }
}
