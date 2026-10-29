<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\ApprovalQueue;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Filament\Pages\P2p\ReviewAccountsPayable;
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
function reviewContext(P2pStatus $status = P2pStatus::SubmittedToAp, array $attributes = []): array
{
    $vendor = Vendor::factory()->create(['name' => 'Northstar Industrial Supply']);
    $company = Company::factory()->create();
    $processor = User::factory()->create()->assignRole(RoleName::ApProcessor);

    $po = SapGoodReceipt::factory()->create([
        'purchase_order' => '4500098412',
        'good_receipt_number' => '5000123',
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
    ]);
    $item = SapGoodReceiptItem::factory()->for($po, 'goodReceipt')->create([
        'purchase_order' => '4500098412',
        'purchase_order_item' => '00010',
        'quantity_po' => 10,
        'quantity_served' => 10,
        'unit_cost' => 100,
    ]);

    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $po->id,
        'purchase_order' => '4500098412',
        'good_receipt_number' => '5000123',
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => P2pStatus::SubmittedToAp,
    ]);

    $invoice = Invoice::factory()->create([
        'user_id' => User::factory()->create(['vendor_id' => $vendor->id])->id,
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
        'status' => $status,
        'po_numbers' => ['4500098412'],
        'gr_numbers' => ['5000123'],
        'reference_number' => ['DR-88931'],
        'amount_submitted' => 1000,
        'submitted_at' => now()->subDay(),
        ...$attributes,
    ]);

    InvoiceLine::factory()->create([
        'p2p_invoice_id' => $invoice->id,
        'p2p_sap_good_receipt_item_id' => $item->id,
        'purchase_order' => '4500098412',
        'purchase_order_item' => '00010',
        'quantity_billed' => 10,
        'unit_cost' => 100,
    ]);

    return [$processor, $invoice, $state];
}

it('opens from the new queue with the invoice, the items and the AP values', function () {
    [$processor, $invoice] = reviewContext();
    $this->actingAs($processor);

    $this->get(ReviewAccountsPayable::getUrl(['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee('Bulk Accounts Payable')
        ->assertSee('Northstar Industrial Supply')
        ->assertSee('Goods Receipt Number 5000123')
        ->assertSee('Purchase Order Number 4500098412')
        ->assertSee('DR-88931')
        ->assertSee('Approval History')
        ->assertSee('No invoice attachment uploaded.');
});

it('shows what Read invoice with AI found', function () {
    [$processor, $invoice] = reviewContext(attributes: [
        'match_flag_count' => 1,
        'invoice_match' => [
            'flags' => [['gr_number' => '5000123', 'item_name' => 'Battery mould', 'purchase_order' => '4500098412', 'purchase_order_item' => '10', 'reasons' => ['Unit cost differs from the PO.']]],
            'vendor_remarks' => 'Surcharge per lead index',
        ],
    ]);
    $this->actingAs($processor);

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id])
        ->assertSee('1 line to check against the vendor')
        ->assertSee('Unit cost differs from the PO.')
        ->assertSee('Surcharge per lead index');
});

it('submits to the checker once a tax code is set, then goes back to the new queue', function () {
    [$processor, $invoice] = reviewContext();
    $this->actingAs($processor);

    TaxCode::factory()->create(['code' => 'I2', 'description' => 'Input VAT 12%', 'rate' => 12]);

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id])
        ->set('data.tax_code', null)
        ->call('submit')
        ->tap(fn () => expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToAp))
        ->set('data.tax_code', 'I2')
        ->call('submit')
        ->assertRedirect(ApprovalQueue::getUrl());

    expect($invoice->fresh())->status->toBe(P2pStatus::SubmittedToChecker)->processed->toBeTrue();
});

it('recalculates the summary as the tax changes', function () {
    [$processor, $invoice] = reviewContext();
    $this->actingAs($processor);

    TaxCode::factory()->create(['code' => 'I2', 'description' => 'Input VAT 12%', 'rate' => 12]);

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id])
        ->fillForm(['tax_code' => 'I2'])
        ->assertSet('data.total', fn ($total): bool => (float) str_replace(',', '', (string) $total) > 0)
        ->assertSee('Net payable');
});

it('escalates only the ticked goods receipts', function () {
    [$processor, $invoice, $state] = reviewContext();
    $this->actingAs($processor);

    EscalationReason::create(['code' => 'qty_issue', 'name' => 'Quantity Issue', 'origin_role' => 'processor', 'is_active' => true])
        ->steps()->create(['step_order' => 1, 'role' => 'procurement', 'status_id' => P2pStatus::EscalateToProcurement->value]);

    $component = Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id]);

    $goodsReceipts = $component->get('data.goods_receipts');
    $goodsReceipts[0]['goodReceiptItems'][0]['escalate'] = true;

    $component->set('data.goods_receipts', $goodsReceipts)
        ->callAction('escalate', ['escalationReason' => 'qty_issue', 'escalationRemarks' => 'Numbers do not agree.']);

    expect($invoice->fresh()->escalations()->count())->toBe(1)
        ->and($state->fresh()->status)->toBe(P2pStatus::EscalateToProcurement);
});

it('returns the submission to the vendor with remarks', function () {
    [$processor, $invoice] = reviewContext();
    $this->actingAs($processor);

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('returnToVendor', ['remarks_ap' => 'Attachment is unreadable.']);

    expect($invoice->fresh()->status)->toBe(P2pStatus::ReturnedToVendor);
});

it('offers the supervisor Approve and Return, and opens other queues view only', function () {
    [, $invoice] = reviewContext(P2pStatus::SubmittedToChecker);
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApSupervisor));

    $actions = collect(Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id])->instance()->getFooterActions())
        ->map->getName()->all();

    expect($actions)->toContain('approve', 'returnToApProcessor', 'back')
        ->and($actions)->not->toContain('submit', 'escalate');

    [$processor, $checkerInvoice] = reviewContext(P2pStatus::SubmittedToChecker);
    $this->actingAs($processor);

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => $checkerInvoice->id])
        ->assertSet('isViewOnly', true)
        ->assertSee('View only: not in your queue');
});

it('goes back to the new queue on Cancel, and to it when the invoice is missing', function () {
    [$processor, $invoice] = reviewContext();
    $this->actingAs($processor);

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('back')
        ->assertRedirect(ApprovalQueue::getUrl());

    Livewire::test(ReviewAccountsPayable::class, ['invoice' => 999999])
        ->assertRedirect(ApprovalQueue::getUrl());
});

it('leaves the old screen going back to the old queue', function () {
    [$processor, $invoice] = reviewContext();
    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('back')
        ->assertRedirect('/hub/p2p/approvals');
});
