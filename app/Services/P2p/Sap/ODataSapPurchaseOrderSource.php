<?php

namespace App\Services\P2p\Sap;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Contracts\P2p\SapReversalSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Real SAP purchase-order source (OData GET).
 *
 * Ported from the legacy `SapPurchaseOrderSyncService` + `SapPurchaseOrderService`.
 * Discovery uses two sources (EKBE history and EKKO header dates) unioned, so
 * brand-new POs and GR activity on older POs are both picked up.
 */
class ODataSapPurchaseOrderSource implements SapPurchaseOrderSource, SapReversalSource
{
    public function __construct(
        private readonly P2pSapPurchaseOrderReader $reader,
        private readonly P2pSapReversalResolver $reversals,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function changedPurchaseOrders(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        $numbers = $this->resolvePurchaseOrderNumbers($filters);

        if ($numbers === []) {
            return [];
        }

        $lookupFilters = $filters;

        if ($numbers !== null) {
            $lookupFilters['po_numbers'] = $numbers;

            // The header lookup already applied these; leaving them in would AND
            // with the EBELN list and drop EKBE-sourced POs created outside it.
            unset($lookupFilters['bedat_from'], $lookupFilters['bedat_to'], $lookupFilters['aedat_from'], $lookupFilters['aedat_to']);
        }

        $result = $this->reader->purchaseOrders($lookupFilters);

        if (! $result['success']) {
            throw new RuntimeException($result['error'] ?? 'Failed to read purchase orders from SAP.');
        }

        $mapped = [];

        foreach ($result['data'] as $purchaseOrder) {
            $mapped[] = $this->mapPurchaseOrder($purchaseOrder);
        }

        return $mapped;
    }

    /**
     * @return array{reversed: array<string, string>, replacements: array<string, string>}
     */
    public function reversalsForPurchaseOrder(string $purchaseOrder): array
    {
        return $this->reversals->resolve($this->reader->reversalRows($purchaseOrder));
    }

    /**
     * Returns null when the caller should query headers directly (no discovery).
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>|null
     */
    protected function resolvePurchaseOrderNumbers(array $filters): ?array
    {
        if (! empty($filters['po_numbers']) && is_array($filters['po_numbers'])) {
            return array_values(array_filter(array_map(
                static fn ($number): string => trim((string) $number),
                $filters['po_numbers'],
            )));
        }

        if (empty($filters['changed_only_history'])) {
            return null;
        }

        $history = $this->reader->changedPurchaseOrderNumbersByHistory($filters);
        $headers = $this->reader->purchaseOrderNumbersByHeaderDates($filters);

        $historyOk = (bool) ($history['success'] ?? false);
        $headersOk = (bool) ($headers['success'] ?? false);

        if (! $historyOk && ! $headersOk) {
            throw new RuntimeException($history['error'] ?? 'Failed to discover changed purchase orders in SAP.');
        }

        $numbers = array_values(array_unique(array_merge(
            $historyOk ? ($history['data'] ?? []) : [],
            $headersOk ? ($headers['data'] ?? []) : [],
        )));

        if ($numbers === []) {
            Log::info('[P2pSap] No changed purchase orders in the requested window.', ['filters' => $filters]);
        }

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $purchaseOrder
     * @return array<string, mixed>
     */
    protected function mapPurchaseOrder(array $purchaseOrder): array
    {
        $vendorCode = trim((string) ($purchaseOrder['vendor'] ?? ''));
        $companyCode = trim((string) ($purchaseOrder['company_code'] ?? ''));

        $deliveredByItem = $purchaseOrder['delivered_by_item'] ?? [];
        $receipts = [];

        foreach (($purchaseOrder['gr_rows'] ?? []) as $grNumber => $rows) {
            $amountTotal = 0.0;
            $dateAt = null;
            $items = [];

            foreach ($rows as $entry) {
                $mseg = $entry['mseg'] ?? [];
                $item = $entry['item'] ?? null;

                if (! is_array($item)) {
                    continue;
                }

                $quantity = (float) ($mseg['MENGE'] ?? $mseg['ERFMG'] ?? 0);
                $lineAmount = (float) ($mseg['DMBTR'] ?? 0);
                $poItem = trim((string) ($mseg['EBELP'] ?? $item['EBELP'] ?? ''));
                $poQuantity = (float) ($item['MENGE'] ?? 0);
                $delivered = (float) ($deliveredByItem[$poItem] ?? 0);

                $amountTotal += $lineAmount;
                $dateAt ??= $this->parseDate($mseg['BUDAT'] ?? null)?->toDateString();

                $items[] = [
                    'material_doc_year' => (int) ($mseg['MJAHR'] ?? 0),
                    'material_doc_item' => (string) ($mseg['ZEILE'] ?? ''),
                    'purchase_order_item' => $poItem,
                    'item_name' => trim((string) ($mseg['MATNR'] ?? '')),
                    'item_description' => trim((string) ($item['TXZ01'] ?? '')),
                    'quantity_po' => $poQuantity,
                    'quantity_served' => $quantity,
                    'uom' => trim((string) ($mseg['MEINS'] ?? $mseg['ERFME'] ?? '')),
                    'gr' => (int) round($quantity),
                    'unit_cost' => $quantity > 0
                        ? round($lineAmount / $quantity, 4)
                        : round((float) ($item['NETPR'] ?? 0), 4),
                    'net_price' => (float) ($item['NETPR'] ?? 0),
                    'price_unit' => max(1, (int) ($item['PEINH'] ?? 1)),
                    'currency' => $purchaseOrder['currency'] ?: null,
                    'amount' => $lineAmount,
                    'quantity_po_balance' => (int) round($poQuantity - $delivered),
                    'storage_location' => trim((string) ($mseg['LGORT'] ?? '')),
                ];
            }

            $receipts[] = [
                'good_receipt_number' => (string) $grNumber,
                'amount_total' => round($amountTotal, 2),
                'date_at' => $dateAt,
                'items' => $items,
            ];
        }

        return [
            'purchase_order' => (string) $purchaseOrder['po_number'],
            'company_code' => $companyCode,
            'vendor_code' => $vendorCode,
            'currency_key' => $purchaseOrder['currency'] ?: null,
            'total_amount' => (float) ($purchaseOrder['total_amount'] ?? 0),
            'purchase_order_date' => $this->parseDate($purchaseOrder['po_date'] ?? null)?->toDateString(),
            'purchase_order_type' => $purchaseOrder['purchase_order_type'] ?: null,
            'changed_on' => $this->parseDate($purchaseOrder['changed_on'] ?? null)?->toDateString(),
            'created_by' => $purchaseOrder['created_by'] ?: null,
            'quantity_po_balance' => (int) round((float) ($purchaseOrder['total_po_balance'] ?? 0)),
            'gr_numbers' => $purchaseOrder['gr_numbers'] ?? [],
            'good_receipts' => $receipts,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function normalizeFilters(array $filters): array
    {
        // The legacy system calls the vendor filter `lifnr`; accept both.
        if (empty($filters['lifnr']) && ! empty($filters['vendor'])) {
            $filters['lifnr'] = $filters['vendor'];
        }

        return $filters;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $value = (string) $value;

        if (preg_match('#/Date\((\d+)([+-]\d+)?\)/#', $value, $matches)) {
            return Carbon::createFromTimestampMs((int) $matches[1]);
        }

        if (strlen($value) === 8 && ctype_digit($value)) {
            return Carbon::createFromFormat('Ymd', $value);
        }

        return Carbon::parse($value);
    }
}
