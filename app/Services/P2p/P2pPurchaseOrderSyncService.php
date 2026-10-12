<?php

namespace App\Services\P2p;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapPurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Applies a batch of SAP purchase-order data (from a {@see SapPurchaseOrderSource})
 * to the mirror tables and opens a pending GR state for each new goods receipt. The
 * derived GR status is never overwritten here — the sync only seeds new pending GRs.
 */
class P2pPurchaseOrderSyncService
{
    public function __construct(private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return int number of purchase orders synced
     */
    public function run(SapPurchaseOrderSource $source, array $filters = []): int
    {
        $purchaseOrders = $source->changedPurchaseOrders($filters);
        $synced = 0;

        foreach ($purchaseOrders as $data) {
            DB::transaction(function () use ($data): void {
                $po = SapPurchaseOrder::query()->updateOrCreate(
                    ['purchase_order' => $data['purchase_order']],
                    [
                        'company_code' => $data['company_code'] ?? null,
                        'vendor_code' => $data['vendor_code'] ?? null,
                        'currency_key' => $data['currency_key'] ?? 'PHP',
                        'total_amount' => $data['total_amount'] ?? null,
                        'purchase_order_date' => $data['purchase_order_date'] ?? null,
                    ],
                );

                foreach ($data['good_receipts'] ?? [] as $gr) {
                    $receipt = SapGoodReceipt::query()->updateOrCreate(
                        ['good_receipt_number' => $gr['good_receipt_number']],
                        [
                            'purchase_order' => $data['purchase_order'],
                            'company_id' => $gr['company_id'] ?? null,
                            'vendor_id' => $gr['vendor_id'] ?? null,
                            'amount_total' => $gr['amount_total'] ?? null,
                        ],
                    );

                    foreach ($gr['items'] ?? [] as $item) {
                        $receipt->items()->updateOrCreate(
                            [
                                'material_doc_year' => $item['material_doc_year'] ?? null,
                                'material_doc_item' => $item['material_doc_item'] ?? null,
                            ],
                            [
                                'purchase_order' => $data['purchase_order'],
                                'purchase_order_item' => $item['purchase_order_item'] ?? null,
                                'storage_location' => $item['storage_location'] ?? null,
                                'item_name' => $item['item_name'] ?? null,
                                'quantity_po' => $item['quantity_po'] ?? null,
                                'quantity_served' => $item['quantity_served'] ?? null,
                                'uom' => $item['uom'] ?? null,
                                'unit_cost' => $item['unit_cost'] ?? null,
                                'amount' => $item['amount'] ?? null,
                            ],
                        );
                    }

                    // Open a pending state for a new GR; never touch one already in flow.
                    GoodReceiptState::query()->firstOrCreate(
                        ['good_receipt_number' => $gr['good_receipt_number']],
                        [
                            'p2p_sap_good_receipt_id' => $receipt->id,
                            'purchase_order' => $data['purchase_order'],
                            'company_id' => $gr['company_id'] ?? null,
                            'vendor_id' => $gr['vendor_id'] ?? null,
                            'amount_total' => $gr['amount_total'] ?? 0,
                        ],
                    );
                }

                $this->purchaseOrderStatus->recalculate($po);
            });

            $synced++;
        }

        return $synced;
    }
}
