<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\ProcessInvoice;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

/**
 * A submitted invoice with one GR line, ready for the AP processor (as in BulkAccountsPayableTest).
 *
 * @return array{0: User, 1: Invoice}
 */
function processInvoiceContext(P2pStatus $status = P2pStatus::SubmittedToAp): array
{
    $vendor = Vendor::factory()->create(['name' => 'Northstar Industrial Supply']);
    $company = Company::factory()->create();
    $processor = User::factory()->create()->assignRole(RoleName::ApProcessor);

    $gr = SapGoodReceipt::factory()->create([
        'purchase_order' => '4500006000',
        'good_receipt_number' => '5000006000',
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
    ]);
    $item = SapGoodReceiptItem::factory()->for($gr, 'goodReceipt')->create([
        'purchase_order' => '4500006000',
        'purchase_order_item' => '00010',
        'quantity_po' => 10,
        'quantity_served' => 10,
        'unit_cost' => 100,
    ]);

    GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $gr->id,
        'purchase_order' => '4500006000',
        'good_receipt_number' => '5000006000',
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => P2pStatus::SubmittedToAp,
    ]);

    $invoice = Invoice::factory()->create([
        'user_id' => User::factory()->create(['vendor_id' => $vendor->id])->id,
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
        'status' => $status,
        'po_numbers' => ['4500006000'],
        'gr_numbers' => ['5000006000'],
        'reference_number' => ['DR-1234'],
        'amount_submitted' => 1000,
        'submitted_at' => now()->subDay(),
    ]);

    InvoiceLine::factory()->create([
        'p2p_invoice_id' => $invoice->id,
        'p2p_sap_good_receipt_item_id' => $item->id,
        'purchase_order' => '4500006000',
        'purchase_order_item' => '00010',
        'quantity_billed' => 10,
        'unit_cost' => 100,
    ]);

    return [$processor, $invoice];
}

it('shows the invoice review in the new layout, with every guide stop on the page', function () {
    [$processor, $invoice] = processInvoiceContext();

    $response = $this->actingAs($processor)
        ->get(ProcessInvoice::getUrl(['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSeeInOrder(['Attached Invoice', 'Accounts Payable', 'Items', 'RFP Down Payments', 'Tax', 'Summary', 'Approval History'])
        ->assertSee('Northstar Industrial Supply')
        ->assertSee('5000006000')
        ->assertSee('DR-1234')
        ->assertSee('Report');

    foreach (collect((new ProcessInvoice)->guideSteps())->reject(fn (array $step): bool => $step['target'] === 'match-alert') as $step) {
        $response->assertSee('data-tour="'.$step['target'].'"', escape: false);
    }
});

it('submits to the checker through the same handler as the old page', function () {
    [$processor, $invoice] = processInvoiceContext();
    TaxCode::factory()->create(['code' => 'I2', 'description' => 'Input VAT 12%', 'rate' => 12]);

    $this->actingAs($processor);

    Livewire::test(ProcessInvoice::class, ['invoice' => $invoice->id])
        ->set('data.tax_code', 'I2')
        ->assertNotSet('data.total', '0.00')
        ->call('submit');

    expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToChecker);
});

it('opens read-only, without the Report tick, outside the viewer queue', function () {
    [$processor, $invoice] = processInvoiceContext(P2pStatus::SubmittedToChecker);

    $this->actingAs($processor);

    Livewire::test(ProcessInvoice::class, ['invoice' => $invoice->id])
        ->assertSet('isViewOnly', true)
        ->assertSee('View only')
        ->assertDontSee('Report this line');
});
