<?php

namespace App\Services\P2p\Sap;

use DateTime;
use Illuminate\Support\Facades\Log;

/**
 * Reads purchase orders and goods receipts from SAP's OData CDS views.
 *
 * Ported from the legacy `SapPurchaseOrderService`, but the per-item MSEG/EKET
 * fan-out is replaced with two per-PO queries so a sync no longer issues one
 * request per line (the legacy 30-minute job problem).
 */
class P2pSapPurchaseOrderReader
{
    private const string EKKO_ENDPOINT = '/sap/opu/odata/sap/ZCDS_MM_EKKO_CDS/ZCDS_MM_EKKO';

    private const string EKPO_ENDPOINT = '/sap/opu/odata/sap/ZCDS_MM_EKPO_CDS/ZCDS_MM_EKPO';

    private const string EKET_ENDPOINT = '/sap/opu/odata/sap/ZCDS_MM_EKET_CDS/ZCDS_MM_EKET';

    private const string MSEG_ENDPOINT = '/sap/opu/odata/sap/ZCDS_MM_MSEG_CDS/ZCDS_MM_MSEG';

    private const string EKBE_ENDPOINT = '/sap/opu/odata/sap/ZCDS_MM_EKBE_CDS/ZCDS_MM_EKBE';

    public function __construct(private readonly P2pSapClient $client) {}

    /**
     * PO numbers with goods-receipt / invoice activity in the EKBE window.
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data: list<string>, error?: string}
     */
    public function changedPurchaseOrderNumbersByHistory(array $filters = []): array
    {
        $parts = [];

        if (! empty($filters['cpudt_from'])) {
            $parts[] = 'CPUDT ge '.$this->odataDate((string) $filters['cpudt_from']);
        }

        if (! empty($filters['cpudt_to'])) {
            $parts[] = 'CPUDT le '.$this->odataDate((string) $filters['cpudt_to'], endOfDay: true);
        }

        if (! empty($filters['cputm_from'])) {
            $parts[] = "CPUTM ge '".trim((string) $filters['cputm_from'])."'";
        }

        if (! empty($filters['cputm_to'])) {
            $parts[] = "CPUTM le '".trim((string) $filters['cputm_to'])."'";
        }

        $result = $this->client->get(self::EKBE_ENDPOINT, $parts === [] ? [] : ['$filter' => implode(' and ', $parts)]);

        if (! $result['success']) {
            return ['success' => false, 'data' => [], 'error' => $result['error'] ?? 'EKBE history request failed'];
        }

        $numbers = collect($result['data'] ?? [])
            ->pluck('EBELN')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['success' => true, 'data' => $numbers];
    }

    /**
     * PO numbers from the EKKO header by creation date and by change date.
     *
     * The two ranges must be separate queries: ANDing them would return the
     * intersection (created AND changed in the window), not the union.
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data: list<string>, errors: list<string>}
     */
    public function purchaseOrderNumbersByHeaderDates(array $filters = []): array
    {
        $from = $filters['bedat_from'] ?? $filters['cpudt_from'] ?? null;
        $to = $filters['bedat_to'] ?? $filters['cpudt_to'] ?? null;

        if (blank($from) || blank($to)) {
            return ['success' => true, 'data' => [], 'errors' => []];
        }

        $base = [];
        foreach (['lifnr', 'bukrs', 'ekorg'] as $key) {
            if (! empty($filters[$key])) {
                $base[$key] = $filters[$key];
            }
        }

        $numbers = [];
        $errors = [];

        foreach (['bedat', 'aedat'] as $dateField) {
            $result = $this->headers($base + [
                "{$dateField}_from" => $from,
                "{$dateField}_to" => $to,
            ]);

            if (! $result['success']) {
                $errors[] = "{$dateField}: ".($result['error'] ?? 'unknown error');

                continue;
            }

            foreach ($result['data'] as $header) {
                $ebeln = trim((string) ($header['EBELN'] ?? ''));

                if ($ebeln !== '') {
                    $numbers[] = $ebeln;
                }
            }
        }

        return [
            'success' => true,
            'data' => array_values(array_unique($numbers)),
            'errors' => $errors,
        ];
    }

    /**
     * PO headers with their items and goods-receipt lines.
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data: list<array<string, mixed>>, error?: string}
     */
    public function purchaseOrders(array $filters = []): array
    {
        $headers = $this->headers($filters);

        if (! $headers['success']) {
            return ['success' => false, 'data' => [], 'error' => $headers['error'] ?? 'Failed to fetch purchase order headers'];
        }

        $summary = [];

        foreach ($headers['data'] as $header) {
            $ebeln = trim((string) ($header['EBELN'] ?? ''));

            if ($ebeln === '') {
                continue;
            }

            $items = $this->items($ebeln);

            if (! $items['success']) {
                Log::warning('[P2pSap] Could not read PO items; skipping PO', ['purchase_order' => $ebeln]);

                continue;
            }

            $itemsByNumber = collect($items['data'])
                ->keyBy(fn (array $item): string => trim((string) ($item['EBELP'] ?? '')));

            $mseg = $this->msegByPurchaseOrder($ebeln);
            $wemng = $this->deliveredQuantities($ebeln);

            $grRows = [];
            $totalAmount = 0.0;

            foreach ($mseg as $row) {
                $grNumber = trim((string) ($row['MBLNR'] ?? ''));

                if ($grNumber === '') {
                    continue;
                }

                $grRows[$grNumber][] = [
                    'mseg' => $row,
                    'item' => $itemsByNumber->get(trim((string) ($row['EBELP'] ?? ''))),
                ];

                $totalAmount += (float) ($row['DMBTR'] ?? 0);
            }

            $totalPoBalance = 0.0;
            foreach ($items['data'] as $item) {
                $ebelp = trim((string) ($item['EBELP'] ?? ''));
                $totalPoBalance += ((float) ($item['MENGE'] ?? 0)) - ($wemng[$ebelp] ?? 0.0);
            }

            $summary[] = [
                'po_number' => $ebeln,
                'po_date' => $header['BEDAT'] ?? null,
                'vendor' => trim((string) ($header['LIFNR'] ?? '')),
                'company_code' => trim((string) ($header['BUKRS'] ?? '')),
                'currency' => trim((string) ($header['WAERS'] ?? '')),
                'purchase_order_type' => trim((string) ($header['BSART'] ?? '')),
                'changed_on' => $header['AEDAT'] ?? null,
                'created_by' => trim((string) ($header['ERNAM'] ?? '')),
                'gr_numbers' => array_map(static fn ($number): string => (string) $number, array_keys($grRows)),
                'gr_count' => count($grRows),
                'total_amount' => round($totalAmount, 2),
                'total_po_balance' => round($totalPoBalance, 2),
                'items' => $items['data'],
                'gr_rows' => $grRows,
                'delivered_by_item' => $wemng,
            ];
        }

        return ['success' => true, 'data' => $summary];
    }

    /**
     * EKBE rows needed to detect GR reversals (movement type 102).
     *
     * @return list<array<string, mixed>>
     */
    public function reversalRows(string $purchaseOrder): array
    {
        $result = $this->client->get(self::EKBE_ENDPOINT, [
            '$filter' => "EBELN eq '{$this->escape($purchaseOrder)}'",
            '$select' => 'EBELN,EBELP,VGABE,BWART,SHKZG,BELNR,LFBNR,LFPOS,BUDAT,MENGE',
        ]);

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data: list<array<string, mixed>>, error?: string}
     */
    public function headers(array $filters = []): array
    {
        $filter = $this->headerFilter($filters);

        $result = $this->client->get(self::EKKO_ENDPOINT, $filter === null ? [] : ['$filter' => $filter]);

        if (! $result['success']) {
            return ['success' => false, 'data' => [], 'error' => $result['error'] ?? 'Failed to fetch PO headers'];
        }

        return ['success' => true, 'data' => is_array($result['data'] ?? null) ? $result['data'] : []];
    }

    /**
     * @return array{success: bool, data: list<array<string, mixed>>}
     */
    public function items(string $purchaseOrder): array
    {
        $result = $this->client->get(self::EKPO_ENDPOINT, [
            '$filter' => "EBELN eq '{$this->escape($purchaseOrder)}'",
        ]);

        return [
            'success' => $result['success'],
            'data' => $result['success'] && is_array($result['data'] ?? null) ? $result['data'] : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function msegByPurchaseOrder(string $purchaseOrder): array
    {
        $result = $this->client->get(self::MSEG_ENDPOINT, [
            '$filter' => "EBELN eq '{$this->escape($purchaseOrder)}' and SHKZG ne 'H'",
        ]);

        return $result['success'] && is_array($result['data'] ?? null)
            ? $this->excludeCancelledRows($result['data'])
            : [];
    }

    /**
     * Sum EKET WEMNG (delivered quantity) per PO item.
     *
     * @return array<string, float>
     */
    public function deliveredQuantities(string $purchaseOrder): array
    {
        $result = $this->client->get(self::EKET_ENDPOINT, [
            '$filter' => "EBELN eq '{$this->escape($purchaseOrder)}'",
        ]);

        if (! $result['success'] || ! is_array($result['data'] ?? null)) {
            return [];
        }

        $totals = [];
        foreach ($result['data'] as $row) {
            $item = trim((string) ($row['EBELP'] ?? ''));
            $totals[$item] = ($totals[$item] ?? 0.0) + (float) ($row['WEMNG'] ?? 0);
        }

        return $totals;
    }

    /**
     * Build the EKKO `$filter` for the supported filter keys.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function headerFilter(array $filters): ?string
    {
        $parts = [];

        foreach (['lifnr' => 'LIFNR', 'bukrs' => 'BUKRS', 'ekorg' => 'EKORG'] as $key => $field) {
            if (! empty($filters[$key])) {
                $parts[] = "{$field} eq '".$this->escape((string) $filters[$key])."'";
            }
        }

        if (! empty($filters['po_numbers']) && is_array($filters['po_numbers'])) {
            $poParts = [];
            foreach (array_unique($filters['po_numbers']) as $poNumber) {
                $poNumber = trim((string) $poNumber);
                if ($poNumber !== '') {
                    $poParts[] = "EBELN eq '".$this->escape($poNumber)."'";
                }
            }

            if ($poParts !== []) {
                $parts[] = '('.implode(' or ', $poParts).')';
            }
        }

        foreach (['bedat', 'aedat'] as $field) {
            if (! empty($filters["{$field}_from"])) {
                $parts[] = strtoupper($field).' ge '.$this->odataDate((string) $filters["{$field}_from"]);
            }

            if (! empty($filters["{$field}_to"])) {
                $parts[] = strtoupper($field).' le '.$this->odataDate((string) $filters["{$field}_to"], endOfDay: true);
            }
        }

        return $parts === [] ? null : implode(' and ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function excludeCancelledRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => strtoupper((string) ($row['SHKZG'] ?? '')) !== 'H',
        ));
    }

    /**
     * OData v2 datetime literal. Accepts `Ymd` or any DateTime-parseable string.
     */
    protected function odataDate(string $date, bool $endOfDay = false): string
    {
        $parsed = strlen($date) === 8 && ctype_digit($date)
            ? DateTime::createFromFormat('Ymd', $date)
            : new DateTime($date);

        if ($parsed === false) {
            $parsed = new DateTime;
        }

        return "datetime'{$parsed->format('Y-m-d')}T".($endOfDay ? '23:59:59' : '00:00:00')."'";
    }

    /**
     * Escape a value used inside a single-quoted OData literal.
     */
    protected function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
