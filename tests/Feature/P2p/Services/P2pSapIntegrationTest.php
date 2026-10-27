<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapApiLog;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\Vendor;
use App\Services\P2p\P2pPurchaseOrderSyncService;
use App\Services\P2p\Sap\ODataSapPurchaseOrderSource;
use App\Services\P2p\Sap\P2pSapPurchaseOrderReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

pest()->use(RefreshDatabase::class);

it('syncs a purchase order and its goods receipt from SAP OData', function () {
    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.username' => 'odata-user',
        'p2p.sap.password' => 'odata-secret',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => true,
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'ZCDS_MM_EKBE') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000500',
                'EBELP' => '00010',
                'BWART' => '101',
                'BELNR' => '5000000500',
                'LFBNR' => '',
                'BUDAT' => '2026-10-20',
                'MENGE' => 10,
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKKO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000500',
                'BUKRS' => '1000',
                'LIFNR' => '200000',
                'WAERS' => 'PHP',
                'BEDAT' => '2026-10-01',
                'AEDAT' => '2026-10-20',
                'BSART' => 'NB',
                'ERNAM' => 'SAPUSER',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKPO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000500',
                'EBELP' => '00010',
                'MENGE' => 10,
                'TXZ01' => 'Widget',
                'NETPR' => 100,
                'PEINH' => 1,
                'MWSKZ' => 'I2',
            ]]]]),
            str_contains($url, 'ZCDS_MM_MSEG') => Http::response(['d' => ['results' => [[
                'MBLNR' => '5000000500',
                'MJAHR' => 2026,
                'ZEILE' => '0001',
                'EBELN' => '4500000500',
                'EBELP' => '00010',
                'MATNR' => 'MAT-1',
                'MENGE' => 10,
                'MEINS' => 'PC',
                'DMBTR' => 1000,
                'LGORT' => 'B001',
                'BUDAT' => '2026-10-20',
                'SHKZG' => 'S',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKET') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000500',
                'EBELP' => '00010',
                'WEMNG' => 10,
            ]]]]),
            default => Http::response(['error' => 'Unexpected SAP endpoint'], 500),
        };
    });

    $company = Company::factory()->create(['code' => '1000']);
    $vendor = Vendor::factory()->create(['code' => '200000']);

    $result = app(P2pPurchaseOrderSyncService::class)->run(
        app(ODataSapPurchaseOrderSource::class),
        [
            'changed_only_history' => true,
            'lifnr' => '200000',
            'cpudt_from' => '20261001',
            'cpudt_to' => '20261022',
        ],
    );

    $purchaseOrder = SapPurchaseOrder::query()->firstOrFail();
    $receipt = SapGoodReceipt::query()->firstOrFail();
    $item = SapGoodReceiptItem::query()->firstOrFail();
    $state = GoodReceiptState::query()->firstOrFail();

    expect($result['synced'])->toBe(1)
        ->and($result['discovered'])->toBe(1)
        ->and($purchaseOrder->company_code)->toBe('1000')
        ->and($purchaseOrder->vendor_code)->toBe('200000')
        ->and($purchaseOrder->currency_key)->toBe('PHP')
        ->and((float) $purchaseOrder->total_amount)->toBe(1000.0)
        ->and($purchaseOrder->gr_numbers)->toBe(['5000000500'])
        ->and($purchaseOrder->status_id)->toBe(P2pPurchaseOrderStatus::Pending)
        ->and($receipt->company_id)->toBe($company->id)
        ->and($receipt->vendor_id)->toBe($vendor->id)
        ->and((float) $receipt->amount_total)->toBe(1000.0)
        ->and((float) $item->quantity_served)->toBe(10.0)
        ->and((float) $item->unit_cost)->toBe(100.0)
        ->and((float) $item->net_price)->toBe(100.0)
        ->and($item->price_unit)->toBe(1)
        ->and($item->storage_location)->toBe('B001')
        ->and((int) $item->quantity_po_balance)->toBe(0)
        ->and($state->status)->toBe(P2pStatus::Pending)
        ->and($state->quantity_total)->toBe(10)
        ->and($state->quantity_served)->toBe(10);

    expect(SapApiLog::query()->ofType(SapApiLog::TYPE_ODATA)->count())->toBeGreaterThan(0)
        ->and(SapApiLog::query()->whereNotNull('error_message')->count())->toBe(0);
});

it('falls back to ERFMG and ERFME for service items with a zero MENGE', function () {
    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.username' => 'odata-user',
        'p2p.sap.password' => 'odata-secret',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => false,
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'ZCDS_MM_EKBE') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000600',
                'EBELP' => '00010',
                'BWART' => '101',
                'BELNR' => '5000000600',
                'LFBNR' => '',
                'BUDAT' => '2026-10-20',
                'MENGE' => 1,
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKKO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000600',
                'BUKRS' => '1000',
                'LIFNR' => '200001',
                'WAERS' => 'PHP',
                'BEDAT' => '2026-10-01',
                'AEDAT' => '2026-10-20',
                'BSART' => 'NB',
                'ERNAM' => 'SAPUSER',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKPO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000600',
                'EBELP' => '00010',
                'MENGE' => 1,
                'TXZ01' => 'Harper Living Room Sofa Set with Coffee',
                'NETPR' => 35000,
                'PEINH' => 1,
                'MWSKZ' => 'I2',
            ]]]]),
            str_contains($url, 'ZCDS_MM_MSEG') => Http::response(['d' => ['results' => [[
                'MBLNR' => '5000000600',
                'MJAHR' => 2026,
                'ZEILE' => '0001',
                'EBELN' => '4500000600',
                'EBELP' => '00010',
                'MATNR' => '',
                'MENGE' => '0.000',
                'ERFMG' => '1.000',
                'MEINS' => '',
                'ERFME' => 'SET',
                'DMBTR' => 35000,
                'LGORT' => '',
                'BUDAT' => '2026-10-20',
                'SHKZG' => 'S',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKET') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000600',
                'EBELP' => '00010',
                'WEMNG' => 1,
            ]]]]),
            default => Http::response(['error' => 'Unexpected SAP endpoint'], 500),
        };
    });

    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => '200001']);

    app(P2pPurchaseOrderSyncService::class)->run(
        app(ODataSapPurchaseOrderSource::class),
        [
            'changed_only_history' => true,
            'lifnr' => '200001',
            'cpudt_from' => '20261001',
            'cpudt_to' => '20261022',
        ],
    );

    $item = SapGoodReceiptItem::query()->firstOrFail();
    $state = GoodReceiptState::query()->firstOrFail();

    expect((float) $item->quantity_served)->toBe(1.0)
        ->and($item->uom)->toBe('SET')
        ->and($item->gr)->toBe(1)
        ->and($state->quantity_served)->toBe(1)
        ->and($state->quantity_total)->toBe(1);
});

it('persists purchase-order amounts that exceed decimal(12,4)', function () {
    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.username' => 'odata-user',
        'p2p.sap.password' => 'odata-secret',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => false,
    ]);

    // A real SAP PO from the 10-month backfill: PHP 4.86 billion.
    $amount = 4858779295.51;

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($amount) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'ZCDS_MM_EKBE') => Http::response(['d' => ['results' => [[
                'EBELN' => '4700126825', 'EBELP' => '00010', 'BWART' => '101',
                'BELNR' => '5000000700', 'LFBNR' => '', 'BUDAT' => '2026-04-02', 'MENGE' => 1,
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKKO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4700126825', 'BUKRS' => '1000', 'LIFNR' => 'AF200A',
                'WAERS' => 'PHP', 'BEDAT' => '2026-04-02', 'AEDAT' => '2026-04-02',
                'BSART' => 'NBJ', 'ERNAM' => 'DYFRPB',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKPO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4700126825', 'EBELP' => '00010', 'MENGE' => 1,
                'TXZ01' => 'Import shipment', 'NETPR' => $amount, 'PEINH' => 1,
            ]]]]),
            str_contains($url, 'ZCDS_MM_MSEG') => Http::response(['d' => ['results' => [[
                'MBLNR' => '5000000700', 'MJAHR' => 2026, 'ZEILE' => '0001',
                'EBELN' => '4700126825', 'EBELP' => '00010', 'MATNR' => 'MAT-9',
                'MENGE' => 1, 'MEINS' => 'PC', 'DMBTR' => $amount, 'BUDAT' => '2026-04-02',
                'SHKZG' => 'S',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKET') => Http::response(['d' => ['results' => [[
                'EBELN' => '4700126825', 'EBELP' => '00010', 'WEMNG' => 1,
            ]]]]),
            default => Http::response(['error' => 'Unexpected SAP endpoint'], 500),
        };
    });

    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => 'AF200A']);

    app(P2pPurchaseOrderSyncService::class)->run(
        app(ODataSapPurchaseOrderSource::class),
        [
            'changed_only_history' => true,
            'lifnr' => 'AF200A',
            'cpudt_from' => '20260402',
            'cpudt_to' => '20260402',
        ],
    );

    $purchaseOrder = SapPurchaseOrder::query()->firstOrFail();
    $item = SapGoodReceiptItem::query()->firstOrFail();
    $state = GoodReceiptState::query()->firstOrFail();

    expect((float) $purchaseOrder->total_amount)->toEqualWithDelta($amount, 0.01)
        ->and((float) $item->amount)->toEqualWithDelta($amount, 0.01)
        ->and((float) $item->unit_cost)->toEqualWithDelta($amount, 0.01)
        ->and((float) $state->amount_total)->toEqualWithDelta($amount, 0.01);
});

it('does not duplicate mirror rows when the same SAP payload is synced twice', function () {
    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => false,
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'ZCDS_MM_EKBE') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000501', 'EBELP' => '00010', 'BWART' => '101',
                'BELNR' => '5000000501', 'LFBNR' => '', 'BUDAT' => '2026-10-20', 'MENGE' => 5,
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKKO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000501', 'BUKRS' => '1000', 'LIFNR' => '200000',
                'WAERS' => 'PHP', 'BEDAT' => '2026-10-01', 'AEDAT' => '2026-10-20',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKPO') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000501', 'EBELP' => '00010', 'MENGE' => 5, 'TXZ01' => 'Widget',
                'NETPR' => 10, 'PEINH' => 1,
            ]]]]),
            str_contains($url, 'ZCDS_MM_MSEG') => Http::response(['d' => ['results' => [[
                'MBLNR' => '5000000501', 'MJAHR' => 2026, 'ZEILE' => '0001',
                'EBELN' => '4500000501', 'EBELP' => '00010', 'MATNR' => 'MAT-1',
                'MENGE' => 5, 'MEINS' => 'PC', 'DMBTR' => 50, 'BUDAT' => '2026-10-20',
            ]]]]),
            str_contains($url, 'ZCDS_MM_EKET') => Http::response(['d' => ['results' => [[
                'EBELN' => '4500000501', 'EBELP' => '00010', 'WEMNG' => 5,
            ]]]]),
            default => Http::response(['error' => 'Unexpected SAP endpoint'], 500),
        };
    });

    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => '200000']);

    $sync = app(P2pPurchaseOrderSyncService::class);
    $source = app(ODataSapPurchaseOrderSource::class);
    $filters = ['changed_only_history' => true, 'lifnr' => '200000', 'cpudt_from' => '20261001', 'cpudt_to' => '20261022'];

    $sync->run($source, $filters);
    $sync->run($source, $filters);

    expect(SapPurchaseOrder::count())->toBe(1)
        ->and(SapGoodReceipt::count())->toBe(1)
        ->and(SapGoodReceiptItem::count())->toBe(1)
        ->and(GoodReceiptState::count())->toBe(1);
});

it('chunks a large purchase-order number lookup to avoid SAP 414 responses', function () {
    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.username' => 'odata-user',
        'p2p.sap.password' => 'odata-secret',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => false,
    ]);

    // A 10-month window easily discovers more POs than one OData URL can hold.
    $numbers = array_map(
        fn (int $index): string => '45'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
        range(1, 250),
    );

    $requestUrls = [];

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$requestUrls) {
        $requestUrls[] = $request->url();

        preg_match_all("/EBELN eq '([^']+)'/", urldecode($request->url()), $matches);

        return Http::response(['d' => ['results' => array_map(
            fn (string $ebeln): array => ['EBELN' => $ebeln, 'BUKRS' => '1000', 'LIFNR' => '200000'],
            $matches[1],
        )]]);
    });

    $result = app(P2pSapPurchaseOrderReader::class)->headers(['po_numbers' => $numbers]);

    expect($result['success'])->toBeTrue()
        ->and($result['data'])->toHaveCount(250)
        ->and($requestUrls)->toHaveCount(3)
        ->and(max(array_map('strlen', $requestUrls)))->toBeLessThan(4000);
});
