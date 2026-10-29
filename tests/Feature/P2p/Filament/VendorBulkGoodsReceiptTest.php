<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\VendorBulkGoodsReceipt;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

/**
 * Two GRs on two POs for one vendor (as in BulkGoodsReceiptTest).
 *
 * @return array{user: User, states: array<int, GoodReceiptState>}
 */
function vendorBulkContext(): array
{
    $vendor = Vendor::factory()->create();
    $company = Company::factory()->create();
    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(Role::findOrCreate(RoleName::Vendor->value, 'web'));

    $states = [];

    foreach (['4500000900', '4500000901'] as $poNumber) {
        SapPurchaseOrder::factory()->create(['purchase_order' => $poNumber]);
        $receipt = SapGoodReceipt::factory()->create(['purchase_order' => $poNumber, 'company_id' => $company->id, 'vendor_id' => $vendor->id]);
        SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create(['purchase_order' => $poNumber, 'quantity_po' => 10, 'quantity_served' => 10, 'unit_cost' => 100]);
        $states[] = GoodReceiptState::create([
            'p2p_sap_good_receipt_id' => $receipt->id,
            'purchase_order' => $poNumber,
            'good_receipt_number' => $receipt->good_receipt_number,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'status' => P2pStatus::Pending,
        ]);
    }

    return ['user' => $user, 'states' => $states];
}

it('shows step 3 with the AI invoice upload and the pinned actions', function () {
    $c = vendorBulkContext();

    $this->actingAs($c['user'])
        ->get(VendorBulkGoodsReceipt::getUrl(['ids' => array_map(fn ($state) => $state->id, $c['states'])]))
        ->assertOk()
        ->assertSee('3. Attach invoice &amp; submit', escape: false)
        ->assertSee('sf-ai-upload')
        ->assertSee('data-tour="ai-upload"', escape: false)
        ->assertSee('sf-ap-actionbar')
        ->assertSee('Submit to AP');
});

it('submits the invoice through the same handler as the old page', function () {
    $c = vendorBulkContext();

    $this->actingAs($c['user']);

    Livewire::test(VendorBulkGoodsReceipt::class, ['ids' => array_map(fn ($state) => $state->id, $c['states'])])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->set('goods_receipts.1.goodReceiptItems.0.include', true)
        ->set('invoiceAt', now()->toDateString())
        ->set('reference_number', ['DR-1'])
        ->set('attachments', ['attachments-pdf/invoice.pdf'])
        ->call('submit')
        ->assertHasNoErrors();

    expect(Invoice::query()->sole())
        ->status->toBe(P2pStatus::SubmittedToAp)
        ->lines()->count()->toBe(2);
});

it('opens a submitted invoice read-only', function () {
    $c = vendorBulkContext();
    $invoice = Invoice::factory()->create(['user_id' => $c['user']->id, 'vendor_id' => $c['user']->vendor_id, 'status' => P2pStatus::SubmittedToAp]);

    $this->actingAs($c['user']);

    Livewire::test(VendorBulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->assertSet('isViewOnly', true);
});

it('keeps the upload field reachable so Read invoice with AI can open', function () {
    $c = vendorBulkContext();

    $this->actingAs($c['user']);

    $page = Livewire::test(VendorBulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])->instance();

    expect($page->getSchemaComponent('content.attachments'))->not->toBeNull();
});
