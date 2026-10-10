<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
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
 * Two GRs on two POs, both for one vendor — the multi-PO case.
 *
 * @return array{user: User, states: array<int, GoodReceiptState>, items: array<int, SapGoodReceiptItem>}
 */
function bulkContext(): array
{
    $vendor = Vendor::factory()->create();
    $company = Company::factory()->create();
    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(Role::findOrCreate(RoleName::Vendor->value, 'web'));

    $states = [];
    $items = [];

    foreach (['4500000900', '4500000901'] as $index => $poNumber) {
        $po = SapPurchaseOrder::factory()->create(['purchase_order' => $poNumber]);
        $receipt = SapGoodReceipt::factory()->create([
            'purchase_order' => $poNumber,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
        ]);
        $items[] = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
            'purchase_order' => $poNumber,
            'quantity_po' => 10,
            'quantity_served' => 10,
            'unit_cost' => 100,
        ]);
        $states[] = GoodReceiptState::create([
            'p2p_sap_good_receipt_id' => $receipt->id,
            'purchase_order' => $poNumber,
            'good_receipt_number' => $receipt->good_receipt_number,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'status' => P2pStatus::Pending,
        ]);
    }

    return compact('user', 'states', 'items');
}

it('processes multiple GRs across POs into one submission', function () {
    $c = bulkContext();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => array_map(fn ($s) => $s->id, $c['states'])])
        ->set('invoiceAt', now()->toDateString())
        ->set('referenceNumbers', 'DR-1, DR-2')
        ->set('selected', [$c['items'][0]->id => true, $c['items'][1]->id => true])
        ->set('quantities', [$c['items'][0]->id => '10', $c['items'][1]->id => '10'])
        ->call('submit')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($invoice->po_numbers)->toHaveCount(2)
        ->and($invoice->lines()->count())->toBe(2)
        ->and($invoice->amount_submitted)->toBe('2000.00');
});

it('requires a reference number before submitting', function () {
    $c = bulkContext();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('invoiceAt', now()->toDateString())
        ->set('referenceNumbers', '')
        ->call('submit')
        ->assertHasErrors(['referenceNumbers' => 'required']);

    expect(Invoice::query()->count())->toBe(0);
});
