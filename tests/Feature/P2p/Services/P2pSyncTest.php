<?php

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Enums\P2pStatus;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\P2p\SapSyncRun;
use App\Models\Vendor;
use App\Services\P2p\P2pPurchaseOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

pest()->use(RefreshDatabase::class);

function fakeSapSource(): SapPurchaseOrderSource
{
    return new class implements SapPurchaseOrderSource
    {
        public function changedPurchaseOrders(array $filters = []): array
        {
            return [[
                'purchase_order' => '4500000500',
                'company_code' => '1000',
                'vendor_code' => '200000',
                'currency_key' => 'PHP',
                'total_amount' => '1000.00',
                'good_receipts' => [[
                    'good_receipt_number' => '5000000500',
                    'amount_total' => '1000.00',
                    'items' => [[
                        'material_doc_year' => 2026,
                        'material_doc_item' => '0001',
                        'item_name' => 'Widget',
                        'quantity_po' => 10,
                        'quantity_served' => 10,
                        'unit_cost' => 100,
                        'amount' => 1000,
                        'storage_location' => 'B001',
                        'uom' => 'PC',
                    ]],
                ]],
            ]];
        }
    };
}

it('syncs a purchase order and opens a pending GR state', function () {
    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => '200000']);

    $result = app(P2pPurchaseOrderSyncService::class)->run(fakeSapSource(), ['vendor' => '200000']);

    $state = GoodReceiptState::query()->first();

    expect($result['synced'])->toBe(1)
        ->and($result['discovered'])->toBe(1)
        ->and(SapPurchaseOrder::query()->where('purchase_order', '4500000500')->exists())->toBeTrue()
        ->and(SapGoodReceipt::query()->where('good_receipt_number', '5000000500')->exists())->toBeTrue()
        ->and(SapGoodReceiptItem::query()->count())->toBe(1)
        ->and($state?->status)->toBe(P2pStatus::Pending);
});

it('re-runs the sync without duplicating mirror rows', function () {
    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => '200000']);

    $sync = app(P2pPurchaseOrderSyncService::class);

    $sync->run(fakeSapSource());
    $sync->run(fakeSapSource());

    expect(SapPurchaseOrder::count())->toBe(1)
        ->and(SapGoodReceipt::count())->toBe(1)
        ->and(SapGoodReceiptItem::count())->toBe(1)
        ->and(GoodReceiptState::count())->toBe(1);
});

it('skips a purchase order whose vendor is not in the master', function () {
    Company::factory()->create(['code' => '1000']);
    config(['p2p.sap.unknown_vendors' => 'skip']);

    $source = new class implements SapPurchaseOrderSource
    {
        public function changedPurchaseOrders(array $filters = []): array
        {
            return [[
                'purchase_order' => '4500000900',
                'company_code' => '1000',
                'vendor_code' => 'UNKNOWN1',
                'currency_key' => 'PHP',
                'good_receipts' => [],
            ]];
        }
    };

    $result = app(P2pPurchaseOrderSyncService::class)->run($source);

    expect($result['synced'])->toBe(0)
        ->and($result['skipped_unknown_vendor'])->toBe(1)
        ->and(SapPurchaseOrder::query()->where('purchase_order', '4500000900')->exists())->toBeFalse();
});

it('creates an inactive stub vendor when configured to do so', function () {
    Company::factory()->create(['code' => '1000']);
    config(['p2p.sap.unknown_vendors' => 'create']);

    $source = new class implements SapPurchaseOrderSource
    {
        public function changedPurchaseOrders(array $filters = []): array
        {
            return [[
                'purchase_order' => '4500000901',
                'company_code' => '1000',
                'vendor_code' => 'UNKNOWN2',
                'currency_key' => 'PHP',
                'good_receipts' => [],
            ]];
        }
    };

    $result = app(P2pPurchaseOrderSyncService::class)->run($source);

    expect($result['synced'])->toBe(1)
        ->and($result['created_vendors'])->toBe(1)
        ->and(Vendor::query()->where('code', 'UNKNOWN2')->where('is_active', false)->exists())->toBeTrue()
        ->and(SapPurchaseOrder::query()->where('purchase_order', '4500000901')->exists())->toBeTrue();
});

it('deduplicates a pending sync per vendor', function () {
    Queue::fake();

    expect(SyncP2pPurchaseOrders::dispatchUnlessPending(['vendor' => '200000']))->toBeTrue()
        ->and(SyncP2pPurchaseOrders::dispatchUnlessPending(['vendor' => '200000']))->toBeFalse();

    Queue::assertPushed(SyncP2pPurchaseOrders::class, 1);
});

it('runs the sync when the job handles and records a run summary', function () {
    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => '200000']);
    $source = fakeSapSource();

    (new SyncP2pPurchaseOrders(['vendor' => '200000']))->handle($source, app(P2pPurchaseOrderSyncService::class));

    expect(SapPurchaseOrder::query()->where('purchase_order', '4500000500')->exists())->toBeTrue()
        ->and(SapSyncRun::query()->latest('id')->value('status'))->toBe('success')
        ->and((int) SapSyncRun::query()->latest('id')->value('synced'))->toBe(1);
});
