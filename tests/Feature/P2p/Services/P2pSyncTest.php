<?php

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Enums\P2pStatus;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
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
    $count = app(P2pPurchaseOrderSyncService::class)->run(fakeSapSource(), ['vendor' => '200000']);

    $state = GoodReceiptState::query()->first();

    expect($count)->toBe(1)
        ->and(SapPurchaseOrder::query()->where('purchase_order', '4500000500')->exists())->toBeTrue()
        ->and(SapGoodReceipt::query()->where('good_receipt_number', '5000000500')->exists())->toBeTrue()
        ->and(SapGoodReceiptItem::query()->count())->toBe(1)
        ->and($state?->status)->toBe(P2pStatus::Pending);
});

it('re-runs the sync without duplicating mirror rows', function () {
    $sync = app(P2pPurchaseOrderSyncService::class);

    $sync->run(fakeSapSource());
    $sync->run(fakeSapSource());

    expect(SapPurchaseOrder::count())->toBe(1)
        ->and(SapGoodReceipt::count())->toBe(1)
        ->and(SapGoodReceiptItem::count())->toBe(1)
        ->and(GoodReceiptState::count())->toBe(1);
});

it('deduplicates a pending sync per vendor', function () {
    Queue::fake();

    expect(SyncP2pPurchaseOrders::dispatchUnlessPending(['vendor' => '200000']))->toBeTrue()
        ->and(SyncP2pPurchaseOrders::dispatchUnlessPending(['vendor' => '200000']))->toBeFalse();

    Queue::assertPushed(SyncP2pPurchaseOrders::class, 1);
});

it('runs the sync when the job handles', function () {
    $source = fakeSapSource();

    (new SyncP2pPurchaseOrders(['vendor' => '200000']))->handle($source, app(P2pPurchaseOrderSyncService::class));

    expect(SapPurchaseOrder::query()->where('purchase_order', '4500000500')->exists())->toBeTrue();
});
