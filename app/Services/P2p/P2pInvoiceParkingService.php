<?php

namespace App\Services\P2p;

use App\Contracts\P2p\SapInvoiceParkingGateway;
use App\Enums\P2pStatus;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapApiLog;
use App\Models\User;
use App\Services\P2p\Sap\P2pSapApiLogsService;
use App\Support\GoodReceiptNumber;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Parks an approved invoice in SAP (the AP Supervisor's "Approve" step).
 *
 * Ported from the legacy BulkAccountsPayable posting flow:
 * - the invoice first moves to PendingPost (21), the legacy "sent to SAP" status;
 * - the SOAP RFC returns INVOICEDOCNUMBER / FISCALYEAR, then the invoice moves to
 *   InvoiceParked (13);
 * - a SOAP failure leaves it at 21 with the error recorded, so AP can retry.
 *
 * The legacy call was synchronous inside the Livewire action; this keeps that
 * behavior for parity, but the transport is isolated in the gateway so it can be
 * moved to a queued job without changing the workflow.
 */
class P2pInvoiceParkingService
{
    public function __construct(
        private readonly SapInvoiceParkingGateway $gateway,
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

        $invoice = DB::transaction(function () use ($invoice, $by, $remarks): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $from = $invoice->status;

            $invoice->forceFill([
                'status' => P2pStatus::PendingPost,
                'processed' => true,
                'sap_park_attempts' => ((int) $invoice->sap_park_attempts) + 1,
                'sap_park_error' => null,
            ])->save();

            $this->writeHistory($invoice, $from, P2pStatus::PendingPost, $by, $remarks);

            return $invoice->fresh();
        });

        if (! config('p2p.sap.enabled')) {
            $this->sapLogs->log(
                invoice: $invoice,
                endpoint: 'park_invoice',
                request: [
                    'purchase_order' => $invoice->po_numbers,
                    'goods_receipts' => array_map(
                        GoodReceiptNumber::base(...),
                        $invoice->gr_numbers ?? [],
                    ),
                    'tax_code' => $invoice->tax_code,
                    'withholding_tax_code' => $invoice->withholding_tax_code,
                    'total' => $invoice->total,
                ],
                response: 'SAP integration is disabled; invoice left in Pending Post (21).',
                type: SapApiLog::TYPE_SOAP,
            );

            return $invoice;
        }

        return $this->sendToSap($invoice, $by, $remarks);
    }

    /**
     * Retry a park that previously failed. The status is already PendingPost.
     */
    public function retry(Invoice $invoice, User $by, string $remarks = 'Retry SAP parking.'): Invoice
    {
        if ($invoice->status !== P2pStatus::PendingPost) {
            throw ValidationException::withMessages([
                'status' => 'Only a Pending Post invoice can be retried.',
            ]);
        }

        $invoice = DB::transaction(function () use ($invoice): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            $invoice->forceFill([
                'sap_park_attempts' => ((int) $invoice->sap_park_attempts) + 1,
                'sap_park_error' => null,
            ])->save();

            return $invoice->fresh();
        });

        return $this->sendToSap($invoice, $by, $remarks);
    }

    protected function sendToSap(Invoice $invoice, User $by, string $remarks): Invoice
    {
        $result = $this->gateway->park($invoice);

        if (! ($result['success'] ?? false)) {
            $error = (string) ($result['error'] ?? 'SAP parking failed.');
            $this->recordFailure($invoice, $error);

            throw new RuntimeException($error);
        }

        $invoiceNumber = trim((string) ($result['invoice_number'] ?? ''));

        if ($invoiceNumber === '') {
            $message = trim((string) ($result['message'] ?? ''));
            $error = 'SAP did not return an invoice number.'.($message !== '' ? ' '.$message : '');
            $this->recordFailure($invoice, $error);

            throw new RuntimeException($error);
        }

        $parked = $this->complete($invoice, $by, $remarks, $result, $invoiceNumber);

        if ($parked->user !== null) {
            Notification::make()
                ->title('Invoice parked in SAP')
                ->body("{$parked->grc_number} was parked".($parked->invoice_number ? " (doc {$parked->invoice_number})" : '').'.')
                ->success()
                ->sendToDatabase($parked->user);
        }

        return $parked;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function complete(Invoice $invoice, User $by, string $remarks, array $result, string $invoiceNumber): Invoice
    {
        return DB::transaction(function () use ($invoice, $by, $remarks, $result, $invoiceNumber): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $from = $invoice->status;

            $invoice->forceFill([
                'status' => P2pStatus::InvoiceParked,
                'invoice_number' => $invoiceNumber,
                'fiscal_year' => is_numeric($result['fiscal_year'] ?? null) ? (int) $result['fiscal_year'] : null,
                'sap_parked_at' => now(),
                'sap_park_error' => null,
            ])->save();

            $this->writeHistory(
                $invoice,
                $from,
                P2pStatus::InvoiceParked,
                $by,
                trim($remarks.' '.($result['message'] ?? '')),
            );

            return $invoice->fresh();
        });
    }

    protected function writeHistory(Invoice $invoice, P2pStatus $from, P2pStatus $to, User $by, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'p2p_invoice_id' => $invoice->id,
            'actioned_by' => $by->id,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'is_visible_to_vendor' => false,
            'is_visible_to_admin' => true,
        ]);
    }

    protected function recordFailure(Invoice $invoice, string $error): void
    {
        $invoice->forceFill(['sap_park_error' => $error])->save();
    }
}
