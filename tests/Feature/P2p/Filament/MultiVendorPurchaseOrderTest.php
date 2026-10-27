<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\P2p\PurchaseOrders\Actions\SyncFromSapAction;
use App\Filament\Resources\P2p\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\P2p\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

function purchaseOrderFor(
    Vendor $vendor,
    Company $company,
    string $purchaseOrder,
    string $currency = 'PHP',
    P2pPurchaseOrderStatus $status = P2pPurchaseOrderStatus::Pending,
): SapPurchaseOrder {
    return SapPurchaseOrder::factory()->create([
        'purchase_order' => $purchaseOrder,
        'company_code' => $company->code,
        'vendor_code' => $vendor->code,
        'currency_key' => $currency,
        'status_id' => $status,
    ]);
}

function goodsReceiptStateFor(Vendor $vendor, Company $company, string $purchaseOrder): GoodReceiptState
{
    $receipt = SapGoodReceipt::factory()->create([
        'purchase_order' => $purchaseOrder,
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
    ]);

    SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => $purchaseOrder,
        'quantity_po' => 10,
        'quantity_served' => 10,
        'unit_cost' => 100,
    ]);

    return GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id,
        'purchase_order' => $purchaseOrder,
        'good_receipt_number' => $receipt->good_receipt_number,
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
    ]);
}

it('scopes purchase orders and goods receipts to every assigned vendor code', function () {
    $company = Company::factory()->create();
    $first = Vendor::factory()->create();
    $second = Vendor::factory()->create();
    $other = Vendor::factory()->create();

    $user = User::factory()->create(['vendor_id' => $first->id]);
    $user->assignRole(RoleName::Vendor);
    $user->vendors()->sync([$first->id, $second->id]);

    $firstPo = purchaseOrderFor($first, $company, '4500000301');
    $secondPo = purchaseOrderFor($second, $company, '4500000302');
    $otherPo = purchaseOrderFor($other, $company, '4500000303');

    $firstGr = goodsReceiptStateFor($first, $company, $firstPo->purchase_order);
    $secondGr = goodsReceiptStateFor($second, $company, $secondPo->purchase_order);
    $otherGr = goodsReceiptStateFor($other, $company, $otherPo->purchase_order);

    $this->actingAs($user);

    $purchaseOrders = PurchaseOrderResource::getEloquentQuery()->pluck('purchase_order')->all();

    expect($purchaseOrders)
        ->toContain($firstPo->purchase_order, $secondPo->purchase_order)
        ->not->toContain($otherPo->purchase_order);

    $goodsReceipts = GoodsReceiptResource::getEloquentQuery()->pluck('id')->all();

    expect($goodsReceipts)
        ->toContain($firstGr->id, $secondGr->id)
        ->not->toContain($otherGr->id);
});

it('falls back to the legacy vendor id when no pivot rows exist', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create(['vendor_id' => $vendor->id]);

    $user->vendors()->detach();

    expect($user->vendorIds())->toBe([$vendor->id])
        ->and($user->vendorCodes())->toBe([$vendor->code]);
});

it('filters purchase orders by company without an apply step', function () {
    $admin = User::factory()->create()->assignRole(RoleName::Admin);

    $first = Company::factory()->create();
    $second = Company::factory()->create();

    $visible = purchaseOrderFor(Vendor::factory()->create(), $first, '4500000311');
    $hidden = purchaseOrderFor(Vendor::factory()->create(), $second, '4500000312');

    $this->actingAs($admin);

    Livewire::test(ListPurchaseOrders::class)
        ->assertCanSeeTableRecords([$visible, $hidden])
        ->filterTable('company_code', $first->code)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
});

it('offers a vendor only their assigned vendor codes and their companies', function () {
    $company = Company::factory()->create();
    $first = Vendor::factory()->create();
    $second = Vendor::factory()->create();
    $other = Vendor::factory()->create();

    $user = User::factory()->create(['vendor_id' => $first->id]);
    $user->assignRole(RoleName::Vendor);
    $user->vendors()->sync([$first->id, $second->id]);

    purchaseOrderFor($first, $company, '4500000321');
    purchaseOrderFor($second, $company, '4500000322');
    purchaseOrderFor($other, Company::factory()->create(), '4500000323');

    expect(array_map('strval', array_keys(PurchaseOrdersTable::vendorFilterOptions($user))))
        ->toEqualCanonicalizing([$first->code, $second->code])
        ->and(array_map('strval', array_keys(PurchaseOrdersTable::companyFilterOptions($user))))
        ->toBe([(string) $company->code]);
});

it('labels the vendor filter with vendor names for admins', function () {
    $admin = User::factory()->create()->assignRole(RoleName::Admin);
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create(['code' => '600904', 'name' => 'Rosendahl Nextrom GmbH']);

    purchaseOrderFor($vendor, $company, '4500000324');

    $options = PurchaseOrdersTable::vendorFilterOptions($admin);

    expect($options)->toHaveKey('600904')
        ->and($options['600904'])->toContain('600904')
        ->and($options['600904'])->toContain('Rosendahl Nextrom GmbH');
});

it('labels the vendor filter with vendor names for suppliers', function () {
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create(['name' => 'Rosendahl Nextrom GmbH']);

    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(RoleName::Vendor);
    $user->vendors()->sync([$vendor->id]);

    purchaseOrderFor($vendor, $company, '4500000325');

    expect(PurchaseOrdersTable::vendorFilterOptions($user)[$vendor->code])
        ->toContain($vendor->name);
});

it('refuses to bulk-process purchase orders with different currencies', function () {
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create();

    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(RoleName::Vendor);

    $php = purchaseOrderFor($vendor, $company, '4500000331', 'PHP');
    $usd = purchaseOrderFor($vendor, $company, '4500000332', 'USD');

    $this->actingAs($user);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableBulkAction('processGrs', [$php, $usd])
        ->assertNotified('Multiple currencies selected');
});

it('queues one SAP sync per assigned vendor code', function () {
    Queue::fake();

    $first = Vendor::factory()->create();
    $second = Vendor::factory()->create();

    $user = User::factory()->create();
    $user->vendors()->sync([$first->id, $second->id]);

    expect(SyncFromSapAction::dispatchFor($user))->toBe(2);

    Queue::assertPushedOn('user-sync', SyncP2pPurchaseOrders::class);
    Queue::assertPushed(SyncP2pPurchaseOrders::class, 2);
    Queue::assertPushed(
        SyncP2pPurchaseOrders::class,
        fn (SyncP2pPurchaseOrders $job): bool => $job->filters['lifnr'] === $first->code,
    );
    Queue::assertPushed(
        SyncP2pPurchaseOrders::class,
        fn (SyncP2pPurchaseOrders $job): bool => $job->filters['lifnr'] === $second->code,
    );
});

it('does not queue a second sync while one is already pending', function () {
    Queue::fake();

    $vendor = Vendor::factory()->create();

    $user = User::factory()->create();
    $user->vendors()->sync([$vendor->id]);

    expect(SyncFromSapAction::dispatchFor($user))->toBe(1)
        ->and(SyncFromSapAction::dispatchFor($user))->toBe(0);

    Queue::assertPushed(SyncP2pPurchaseOrders::class, 1);
});

it('queues nothing when the login has no vendor code', function () {
    Queue::fake();

    $user = User::factory()->create();

    expect(SyncFromSapAction::dispatchFor($user))->toBe(0);

    Queue::assertNothingPushed();
});

it('memoises vendor assignments to avoid N+1 scoping queries', function () {
    $first = Vendor::factory()->create();
    $second = Vendor::factory()->create();

    $user = User::factory()->create(['vendor_id' => $first->id]);
    $user->vendors()->sync([$first->id, $second->id]);
    $user->unsetRelation('vendors');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $user->vendorIds();
    $user->vendorIds();
    $user->vendorCodes();
    $user->vendorCodes();

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($user->vendorIds())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($user->vendorCodes())->toEqualCanonicalizing([$first->code, $second->code])
        ->and($queries)->toHaveCount(2);
});
