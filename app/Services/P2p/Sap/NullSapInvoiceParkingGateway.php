<?php

namespace App\Services\P2p\Sap;

use App\Contracts\P2p\SapInvoiceParkingGateway;
use App\Models\P2p\Invoice;

/**
 * Used while SAP is disabled. The supervisor's approval still moves the invoice
 * to PendingPost (21) so the workflow and demo data keep working; no network call
 * is made and no SAP document number is produced.
 */
class NullSapInvoiceParkingGateway implements SapInvoiceParkingGateway
{
    public function park(Invoice $invoice): array
    {
        return [
            'success' => true,
            'invoice_number' => null,
            'fiscal_year' => null,
            'message' => 'SAP integration is disabled.',
        ];
    }
}
