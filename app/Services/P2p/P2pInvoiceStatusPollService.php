<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Services\P2p\Sap\P2pAcdocaService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Advances parked invoices from SAP ACDOCA.
 *
 * Ported from the legacy `SyncAcdocaDocuments` command:
 *   21 PendingPost → 13 InvoiceParked  (parked document confirmed)
 *   13 InvoiceParked → 14 InvoicePosted (BELNR found)
 *   14 InvoicePosted → 6 Completed (AUGBL found)
 *
 * One step per invoice per run, exactly like the legacy system, so a posting and
 * its clearing are never collapsed into a single hop.
 */
class P2pInvoiceStatusPollService
{
    public function __construct(
        private readonly P2pAcdocaService $acdoca,
        private readonly P2pGoodReceiptStatusService $goodReceiptStatus,
        private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus,
    ) {}

    /**
     * @return array{checked: int, parked: int, posted: int, completed: int, skipped: int, failed: int}
     */
    public function poll(int $limit = 50): array
    {
        $invoices = Invoice::query()
            ->with('company')
            ->whereIn('status', [
                P2pStatus::PendingPost->value,
                P2pStatus::InvoiceParked->value,
                P2pStatus::InvoicePosted->value,
            ])
            ->whereNotNull('invoice_number')
            ->orderBy('updated_at')
            ->limit(max(1, $limit))
            ->get();

        $counts = [
            'checked' => 0,
            'parked' => 0,
            'posted' => 0,
            'completed' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($invoices as $invoice) {
            $counts['checked']++;

            try {
                $result = $this->pollInvoice($invoice);

                if ($result !== null) {
                    $counts[$result]++;
                } else {
                    $counts['skipped']++;
                }
            } catch (\Throwable $exception) {
                $counts['failed']++;

                Log::error('[P2pAcdoca] Invoice status poll failed.', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    /**
     * @return 'parked'|'posted'|'completed'|null
     */
    protected function pollInvoice(Invoice $invoice): ?string
    {
        if (blank($invoice->invoice_number) || blank($invoice->company?->code) || blank($invoice->fiscal_year)) {
            return null;
        }

        $result = $this->acdoca->getAccountingDocuments(
            awref: (string) $invoice->invoice_number,
            rbukrs: (string) $invoice->company->code,
            aworg: (string) $invoice->fiscal_year,
        );

        if (! $result['success']) {
            return null;
        }

        $rows = $result['data'] ?? [];

        if ($rows === []) {
            // Some CDS views populate GJAHR rather than AWORG; try once more.
            $result = $this->acdoca->getAccountingDocuments(
                awref: (string) $invoice->invoice_number,
                rbukrs: (string) $invoice->company->code,
                gjahr: (string) $invoice->fiscal_year,
            );

            if (! $result['success']) {
                return null;
            }

            $rows = $result['data'] ?? [];
        }

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];
        $belnr = trim((string) ($row['BELNR'] ?? ''));
        $augbl = trim((string) ($row['AUGBL'] ?? ''));

        return match ($invoice->status) {
            P2pStatus::PendingPost => $this->advance($invoice, P2pStatus::InvoiceParked, [], 'SAP ACDOCA: parked document confirmed.'),
            P2pStatus::InvoiceParked => $belnr !== ''
                ? $this->advance($invoice, P2pStatus::InvoicePosted, ['fi_doc_no' => $belnr], "SAP ACDOCA: FI document {$belnr}.")
                : null,
            P2pStatus::InvoicePosted => $augbl !== ''
                ? $this->advance($invoice, P2pStatus::Completed, ['clearing_doc_no' => $augbl, 'completed_at' => now()], "SAP ACDOCA: cleared by {$augbl}.")
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return 'parked'|'posted'|'completed'
     */
    protected function advance(Invoice $invoice, P2pStatus $to, array $attributes, string $remarks): string
    {
        return DB::transaction(function () use ($invoice, $to, $attributes, $remarks): string {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $from = $invoice->status;

            if ($from === $to) {
                return match ($to) {
                    P2pStatus::InvoiceParked => 'parked',
                    P2pStatus::InvoicePosted => 'posted',
                    default => 'completed',
                };
            }

            $invoice->forceFill(array_merge(['status' => $to], $attributes))->save();

            ApprovalHistory::query()->create([
                'p2p_invoice_id' => $invoice->id,
                'actioned_by' => null,
                'from_status' => $from,
                'to_status' => $to,
                'remarks' => $remarks,
                'is_visible_to_vendor' => true,
                'is_visible_to_admin' => true,
            ]);

            $this->goodReceiptStatus->recalculateForInvoice($invoice);

            SapPurchaseOrder::query()
                ->whereIn('purchase_order', $invoice->po_numbers ?? [])
                ->get()
                ->each(fn (SapPurchaseOrder $po) => $this->purchaseOrderStatus->recalculate($po));

            if ($invoice->user !== null) {
                $title = match ($to) {
                    P2pStatus::InvoiceParked => 'Invoice parked in SAP',
                    P2pStatus::InvoicePosted => 'Invoice posted in SAP',
                    default => 'Invoice cleared in SAP',
                };

                Notification::make()
                    ->title($title)
                    ->body("{$invoice->grc_number}: {$remarks}")
                    ->success()
                    ->sendToDatabase($invoice->user);
            }

            return match ($to) {
                P2pStatus::InvoiceParked => 'parked',
                P2pStatus::InvoicePosted => 'posted',
                default => 'completed',
            };
        });
    }
}
