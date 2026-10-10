<?php

namespace App\Contracts\P2p;

/**
 * Source of SAP purchase-order data for the P2P sync. The real implementation reads
 * SAP OData (docs/p2p/07-sap-integration.md); until the integration lands, a null/stub
 * implementation returns no data so the app runs on seeded demo records.
 */
interface SapPurchaseOrderSource
{
    /**
     * POs changed in the window, with their goods receipts and items.
     *
     * @param  array{vendor?: string, changed_from?: string, changed_to?: string}  $filters
     * @return array<int, array{
     *     purchase_order: string,
     *     company_code?: string,
     *     vendor_code?: string,
     *     currency_key?: string,
     *     total_amount?: string|float,
     *     purchase_order_date?: string,
     *     good_receipts?: array<int, array{
     *         good_receipt_number: string,
     *         company_id?: int,
     *         vendor_id?: int,
     *         amount_total?: string|float,
     *         items?: array<int, array<string, mixed>>,
     *     }>,
     * }>
     */
    public function changedPurchaseOrders(array $filters = []): array;
}
