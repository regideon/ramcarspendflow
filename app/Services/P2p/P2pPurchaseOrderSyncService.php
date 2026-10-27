<?php

namespace App\Services\P2p;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Contracts\P2p\SapReversalSource;
use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies a batch of SAP purchase-order data (from a {@see SapPurchaseOrderSource})
 * to the mirror tables and opens a pending GR state for each new goods receipt.
 *
 * Ported from the legacy `SapPurchaseOrderSyncService`: the mirror is never
 * authoritative, and a GR already in flow is never reset by a re-sync.
 */
class P2pPurchaseOrderSyncService
{
    public function __construct(
        private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus,
        private readonly P2pReversalDetectionService $reversals,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     discovered: int,
     *     synced: int,
     *     skipped_unknown_vendor: int,
     *     skipped_unknown_company: int,
     *     created_vendors: int,
     *     reversals: int,
     *     errors: int
     * }
     */
    public function run(SapPurchaseOrderSource $source, array $filters = []): array
    {
        $purchaseOrders = $source->changedPurchaseOrders($filters);

        $stats = [
            'discovered' => count($purchaseOrders),
            'synced' => 0,
            'skipped_unknown_vendor' => 0,
            'skipped_unknown_company' => 0,
            'created_vendors' => 0,
            'reversals' => 0,
            'errors' => 0,
        ];

        foreach ($purchaseOrders as $data) {
            try {
                $data = $this->withResolvedIds($data, $stats);

                if ($data === null) {
                    continue;
                }

                DB::transaction(function () use ($data): void {
                    $this->syncPurchaseOrder($data);
                });

                $stats['synced']++;

                // Reversals are resolved per PO after its GRs are in the mirror so
                // the original GR can be found. A failure must not roll back the PO.
                if ($source instanceof SapReversalSource) {
                    try {
                        $detected = $source->reversalsForPurchaseOrder((string) $data['purchase_order']);
                        $stats['reversals'] += $this->reversals->detect($detected['reversed'], $detected['replacements']);
                    } catch (Throwable $exception) {
                        Log::error('[P2pSap] Reversal detection failed.', [
                            'purchase_order' => $data['purchase_order'] ?? null,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                $stats['errors']++;

                Log::error('[P2pSap] Purchase-order sync failed.', [
                    'purchase_order' => $data['purchase_order'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $stats
     * @return array<string, mixed>|null
     */
    protected function withResolvedIds(array $data, array &$stats): ?array
    {
        $vendorCode = trim((string) ($data['vendor_code'] ?? ''));
        $vendorId = $data['vendor_id'] ?? $this->idForVendor($vendorCode);

        if ($vendorId === null) {
            if ($vendorCode !== '' && config('p2p.sap.unknown_vendors', 'skip') === 'create') {
                $vendor = Vendor::query()->firstOrCreate(
                    ['code' => $vendorCode],
                    ['name' => $vendorCode, 'is_active' => false],
                );

                $vendorId = $vendor->id;
                $stats['created_vendors']++;

                Log::info('[P2pSap] Created an inactive stub vendor for an unknown SAP vendor.', [
                    'vendor' => $vendorCode,
                    'purchase_order' => $data['purchase_order'] ?? null,
                ]);
            } else {
                $stats['skipped_unknown_vendor']++;

                Log::warning('[P2pSap] Skipped PO with an unknown vendor.', [
                    'vendor' => $vendorCode,
                    'purchase_order' => $data['purchase_order'] ?? null,
                ]);

                return null;
            }
        }

        $companyCode = trim((string) ($data['company_code'] ?? ''));
        $companyId = $data['company_id'] ?? $this->idForCompany($companyCode);

        if ($companyId === null) {
            $stats['skipped_unknown_company']++;

            Log::warning('[P2pSap] Skipped PO with an unknown company.', [
                'company' => $companyCode,
                'purchase_order' => $data['purchase_order'] ?? null,
            ]);

            return null;
        }

        $data['vendor_id'] = $vendorId;
        $data['company_id'] = $companyId;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncPurchaseOrder(array $data): void
    {
        $companyId = $data['company_id'];
        $vendorId = $data['vendor_id'];
        $grNumbers = $data['gr_numbers'] ?? array_keys($data['good_receipts'] ?? []);

        $po = SapPurchaseOrder::query()->updateOrCreate(
            ['purchase_order' => $data['purchase_order']],
            [
                'company_code' => $data['company_code'] ?? null,
                'vendor_code' => $data['vendor_code'] ?? null,
                'currency_key' => $data['currency_key'] ?? 'PHP',
                'total_amount' => $data['total_amount'] ?? null,
                'purchase_order_date' => $data['purchase_order_date'] ?? null,
                'purchase_order_type' => $data['purchase_order_type'] ?? null,
                'changed_on' => $data['changed_on'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'gr_numbers' => $grNumbers !== [] ? $grNumbers : null,
                'quantity_po_balance' => $data['quantity_po_balance'] ?? null,
            ],
        );

        foreach (($data['good_receipts'] ?? []) as $gr) {
            $receipt = SapGoodReceipt::query()->updateOrCreate(
                ['good_receipt_number' => $gr['good_receipt_number']],
                [
                    'purchase_order' => $data['purchase_order'],
                    'company_id' => $companyId,
                    'vendor_id' => $vendorId,
                    'reference_number' => $gr['reference_number'] ?? null,
                    'amount_total' => $gr['amount_total'] ?? null,
                    'due_at' => $gr['due_at'] ?? null,
                    'date_at' => $gr['date_at'] ?? null,
                    'upload_at' => $gr['upload_at'] ?? null,
                    'delivery_type_id' => $gr['delivery_type_id'] ?? null,
                ],
            );

            foreach (($gr['items'] ?? []) as $item) {
                $receipt->items()->updateOrCreate(
                    [
                        'material_doc_year' => $item['material_doc_year'] ?? null,
                        'material_doc_item' => $item['material_doc_item'] ?? null,
                    ],
                    [
                        'purchase_order' => $data['purchase_order'],
                        'purchase_order_item' => $item['purchase_order_item'] ?? null,
                        'item_numbering' => $item['item_numbering'] ?? null,
                        'item_id' => $item['item_id'] ?? null,
                        'storage_location' => $item['storage_location'] ?? null,
                        'item_name' => $item['item_name'] ?? null,
                        'item_description' => $item['item_description'] ?? null,
                        'quantity_po' => $item['quantity_po'] ?? null,
                        'quantity_served' => $item['quantity_served'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'gr' => $item['gr'] ?? null,
                        'unit_cost' => $item['unit_cost'] ?? null,
                        'net_price' => $item['net_price'] ?? null,
                        'price_unit' => $item['price_unit'] ?? 1,
                        'currency' => $item['currency'] ?? null,
                        'amount' => $item['amount'] ?? null,
                        'quantity_po_balance' => $item['quantity_po_balance'] ?? null,
                    ],
                );
            }

            $this->openOrRefreshState($receipt, $data, $companyId, $vendorId);
        }

        $this->purchaseOrderStatus->recalculate($po);
    }

    protected function openOrRefreshState(
        SapGoodReceipt $receipt,
        array $data,
        ?int $companyId,
        ?int $vendorId,
    ): void {
        $state = GoodReceiptState::query()
            ->where('good_receipt_number', $receipt->good_receipt_number)
            ->first();

        $quantities = $receipt->items()->get();
        $quantityServed = (int) round((float) $quantities->sum('quantity_served'));
        $quantityTotal = (int) round((float) $quantities->sum('quantity_po'));

        if ($state === null) {
            GoodReceiptState::query()->create([
                'p2p_sap_good_receipt_id' => $receipt->id,
                'purchase_order' => $data['purchase_order'],
                'good_receipt_number' => $receipt->good_receipt_number,
                'company_id' => $companyId,
                'vendor_id' => $vendorId,
                'status' => P2pStatus::Pending,
                'quantity_total' => $quantityTotal,
                'quantity_served' => $quantityServed,
                'amount_total' => $receipt->amount_total ?? 0,
            ]);

            return;
        }

        // Never reset a GR that AP or a vendor is already working on. The mirror
        // can still refresh; only the derived state is protected.
        if (! in_array($state->status, [P2pStatus::Pending, P2pStatus::ReturnedToVendor], true)) {
            return;
        }

        $state->forceFill([
            'p2p_sap_good_receipt_id' => $receipt->id,
            'purchase_order' => $data['purchase_order'],
            'company_id' => $companyId,
            'vendor_id' => $vendorId,
            'quantity_total' => $quantityTotal,
            'quantity_served' => $quantityServed,
            'amount_total' => $receipt->amount_total ?? 0,
        ])->save();
    }

    protected function idForCompany(?string $code): ?int
    {
        return blank($code) ? null : Company::query()->where('code', $code)->value('id');
    }

    protected function idForVendor(?string $code): ?int
    {
        return blank($code) ? null : Vendor::query()->where('code', $code)->value('id');
    }
}
