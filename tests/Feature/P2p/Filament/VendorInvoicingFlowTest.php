<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\VendorBulkGoodsReceipt;
use App\Filament\Pages\P2p\VendorGoodsReceipts;
use App\Filament\Pages\P2p\VendorInvoices;
use App\Filament\Pages\P2p\VendorPurchaseOrders;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);

    $this->vendor = Vendor::factory()->create(['code' => '2000123']);
    $this->company = Company::factory()->create();
    $this->vendorUser = User::factory()->create(['vendor_id' => $this->vendor->id])->assignRole(RoleName::Vendor);
});

function flowReceipt(string $purchaseOrder, string $grNumber, P2pStatus $status = P2pStatus::Pending): GoodReceiptState
{
    return GoodReceiptState::create([
        'purchase_order' => $purchaseOrder,
        'good_receipt_number' => $grNumber,
        'company_id' => test()->company->id,
        'vendor_id' => test()->vendor->id,
        'status' => $status,
    ]);
}

it('lists the vendor\'s invoices on the new page and starts a new one with Process', function () {
    $returned = Invoice::factory()->create(['user_id' => $this->vendorUser->id, 'vendor_id' => $this->vendor->id, 'status' => P2pStatus::ReturnedToVendor, 'reference_number' => ['INV-RETURNED-1']]);
    $inProgress = Invoice::factory()->create(['user_id' => $this->vendorUser->id, 'vendor_id' => $this->vendor->id, 'status' => P2pStatus::SubmittedToAp, 'reference_number' => ['INV-PROGRESS-1']]);

    $this->actingAs($this->vendorUser)
        ->get(VendorInvoices::getUrl())
        ->assertOk()
        ->assertSee('INV-RETURNED-1')
        ->assertSee('Resubmit')
        ->assertSee(VendorPurchaseOrders::getUrl(), escape: false);

    Livewire::test(VendorInvoices::class)
        ->call('setTab', 'returned')
        ->assertSee('INV-RETURNED-1')
        ->assertDontSee('INV-PROGRESS-1')
        ->assertActionHasUrl('process', VendorPurchaseOrders::getUrl());
});

it('shows only the vendor\'s purchase orders and sends the ticked ones to the new Goods Receipts page', function () {
    $mine = SapPurchaseOrder::factory()->create(['vendor_code' => '2000123', 'purchase_order' => '4100385065', 'status_id' => P2pPurchaseOrderStatus::Pending]);
    $done = SapPurchaseOrder::factory()->create(['vendor_code' => '2000123', 'purchase_order' => '4100382699', 'status_id' => P2pPurchaseOrderStatus::Completed]);
    $someoneElses = SapPurchaseOrder::factory()->create(['vendor_code' => '2999999', 'purchase_order' => '4100999999']);

    $this->actingAs($this->vendorUser);

    Livewire::test(VendorPurchaseOrders::class)
        ->assertSee('4100385065')
        ->assertDontSee('4100999999')
        ->call('setTab', 'pending_and_partial')
        ->assertSee('4100385065')
        ->assertDontSee('4100382699')
        ->set('selected', ['4100385065', '4100382699'])
        ->call('processGrs')
        ->assertRedirect(VendorGoodsReceipts::getUrl(['purchase_orders' => ['4100385065', '4100382699']]));
});

it('scopes the new Goods Receipts page to the picked POs and processes the ticked GRs on the goods-receipt screen', function () {
    $first = flowReceipt('4100385065', '5000123456');
    $second = flowReceipt('4100382699', '5000124810');
    $otherPo = flowReceipt('4100000001', '5000000001');
    $submitted = flowReceipt('4100385065', '5000123457', P2pStatus::SubmittedToAp);

    $this->actingAs($this->vendorUser);

    Livewire::withQueryParams(['purchase_orders' => ['4100385065', '4100382699']])
        ->test(VendorGoodsReceipts::class)
        ->assertSet('tab', 'pending')
        ->assertSee('5000123456')
        ->assertSee('5000124810')
        ->assertDontSee('5000000001')
        ->assertDontSee('5000123457')
        ->call('setTab', 'all')
        ->assertSee('5000123457')
        ->call('removePurchaseOrder', '4100382699')
        ->assertDontSee('5000124810')
        ->set('selected', [(string) $first->id])
        ->call('processSelected')
        ->assertRedirect(VendorBulkGoodsReceipt::getUrl(['ids' => [$first->id]]));
});

it('matches a GR number against its partially-billed suffix', function () {
    $partial = flowReceipt('4100000200', '5000000200-P1');
    flowReceipt('4100000201', '5000000201');

    $this->actingAs($this->vendorUser);

    Livewire::withQueryParams(['activeTab' => 'all', 'gr_numbers' => ['5000000200']])
        ->test(VendorGoodsReceipts::class)
        ->assertSee('5000000200-P1')
        ->assertDontSee('5000000201');
});

it('keeps the new vendor pages away from AP and requestor roles', function (string $page) {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'))
        ->get($page::getUrl())
        ->assertForbidden();
})->with([VendorPurchaseOrders::class, VendorGoodsReceipts::class]);
