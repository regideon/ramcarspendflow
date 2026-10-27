<?php

namespace App\Contracts\P2p;

use App\Models\P2p\Invoice;

/**
 * Posts a parked invoice to SAP. The real implementation uses the SOAP RFC
 * ZMM_FM_P2P_INCOMINGINVOICE (ACTION=PARK); the null implementation keeps the
 * portal usable while SAP is disabled.
 */
interface SapInvoiceParkingGateway
{
    /**
     * @return array{
     *     success: bool,
     *     invoice_number?: ?string,
     *     fiscal_year?: ?string,
     *     message?: ?string,
     *     error?: ?string,
     *     http_status?: ?int
     * }
     */
    public function park(Invoice $invoice): array;
}
