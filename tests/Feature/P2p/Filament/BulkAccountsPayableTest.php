<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Models\Company;
use App\Models\P2p\EscalationReason;
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
 * A submitted invoice with one GR line, ready for the AP processor.
 *
 * @return array{0: User, 1: Invoice, 2: GoodReceiptState}
 */
function apPageContext(P2pStatus $status = P2pStatus::SubmittedToAp): array
{
    $vendor = Vendor::factory()->create();
    $company = Company::factory()->create();
    $processor = User::factory()->create()->assignRole(RoleName::ApProcessor);
    $submittedBy = User::factory()->create(['vendor_id' => $vendor->id]);

    $po = SapGoodReceipt::factory()->create([
        'purchase_order' => '4500006000',
        'good_receipt_number' => '5000006000',
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
    ]);
    $item = SapGoodReceiptItem::factory()->for($po, 'goodReceipt')->create([
        'purchase_order' => '4500006000',
        'purchase_order_item' => '00010',
        'quantity_po' => 10,
        'quantity_served' => 10,
        'unit_cost' => 100,
    ]);

    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $po->id,
        'purchase_order' => '4500006000',
        'good_receipt_number' => '5000006000',
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => P2pStatus::SubmittedToAp,
    ]);

    $invoice = Invoice::factory()->create([
        'user_id' => $submittedBy->id,
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

    return [$processor, $invoice, $state];
}

it('renders the side-by-side comparison with the submitted lines', function () {
    [$processor, $invoice] = apPageContext();

    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->assertOk()
        ->assertSee('PO / GR comparison')
        ->assertSee('5000006000');
});

it('requires a tax code before submitting to the checker', function () {
    [$processor, $invoice] = apPageContext();
    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->call('submit')
        ->assertHasErrors(['tax_code' => 'required']);

    expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});

it('moves the submission to the checker once a tax code is set', function () {
    [$processor, $invoice] = apPageContext();
    $this->actingAs($processor);

    TaxCode::factory()->create(['code' => 'I2', 'description' => 'Input VAT 12%', 'rate' => 12]);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->set('tax_code', 'I2')
        ->call('submit');

    $invoice->refresh();

    expect($invoice->status)->toBe(P2pStatus::SubmittedToChecker)
        ->and($invoice->processed)->toBeTrue()
        ->and((float) $invoice->total)->toBeGreaterThan(0);
});

it('escalates only the ticked goods receipts', function () {
    [$processor, $invoice, $state] = apPageContext();
    $this->actingAs($processor);

    EscalationReason::create([
        'code' => 'qty_issue',
        'name' => 'Quantity Issue',
        'origin_role' => 'processor',
        'is_active' => true,
    ])->steps()->create([
        'step_order' => 1,
        'role' => 'procurement',
        'status_id' => P2pStatus::EscalateToProcurement->value,
    ]);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id]);

    // Nothing ticked: the escalation must be refused.
    $component->callAction('escalate', [
        'escalationReason' => 'qty_issue',
        'escalationRemarks' => 'Numbers do not agree.',
    ]);

    expect($invoice->fresh()->escalations()->count())->toBe(0);

    // Tick the line, then escalate.
    $goodsReceipts = $component->get('goods_receipts');
    $goodsReceipts[0]['goodReceiptItems'][0]['escalate'] = true;
    $component->set('goods_receipts', $goodsReceipts);

    $component->callAction('escalate', [
        'escalationReason' => 'qty_issue',
        'escalationRemarks' => 'Numbers do not agree.',
    ]);

    expect($invoice->fresh()->escalations()->count())->toBe(1)
        ->and($state->fresh()->status)->toBe(P2pStatus::EscalateToProcurement);
});

it('returns the submission to the vendor with remarks', function () {
    [$processor, $invoice] = apPageContext();
    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('returnToVendor', ['remarks_ap' => 'Attachment is unreadable.']);

    expect($invoice->fresh()->status)->toBe(P2pStatus::ReturnedToVendor);
});
