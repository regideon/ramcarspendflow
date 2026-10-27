<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Models\Company;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\P2p\P2pGoodReceiptReturnService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

/**
 * Two GRs on two POs, both for one vendor — the multi-PO case.
 *
 * @return array{user: User, vendor: Vendor, company: Company, states: array<int, GoodReceiptState>, items: array<int, SapGoodReceiptItem>}
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

    return compact('user', 'vendor', 'company', 'states', 'items');
}

function bulkEscalationReason(): EscalationReason
{
    $reason = EscalationReason::create([
        'code' => 'quantity_difference',
        'name' => 'Quantity Difference',
        'origin_role' => 'vendor',
        'is_active' => true,
    ]);

    $reason->steps()->create(['step_order' => 1, 'role' => 'warehouse', 'status_id' => P2pStatus::EscalatedToWarehouse->value]);

    return $reason;
}

it('processes multiple GRs across POs into one submission', function () {
    $c = bulkContext();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => array_map(fn ($s) => $s->id, $c['states'])])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->set('goods_receipts.1.goodReceiptItems.0.include', true)
        ->set('invoiceAt', now()->toDateString())
        ->set('reference_number', ['DR-1', 'DR-2'])
        ->set('attachments', ['attachments-pdf/invoice.pdf'])
        ->call('submit')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($invoice->po_numbers)->toHaveCount(2)
        ->and($invoice->reference_number)->toBe(['DR-1', 'DR-2'])
        ->and($invoice->attachments)->toBe(['attachments-pdf/invoice.pdf'])
        ->and($invoice->lines()->count())->toBe(2)
        ->and($invoice->amount_submitted)->toBe('2000.00');
});

it('shows the SAP short text and the material number separately', function () {
    $c = bulkContext();
    $c['items'][0]->forceFill([
        'item_name' => 'OS01050',
        'item_description' => 'CLNMATL GLASS CLEANER, MR.MUSCLE 500ML*',
    ])->save();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->assertSee('SAP Short Text')
        ->assertSee('CLNMATL GLASS CLEANER')
        ->assertSee('OS01050');
});

it('does not leave a draft invoice when the submission is rejected', function () {
    $c = bulkContext();

    // Billed quantity exceeds what the GR still has available.
    $c['items'][0]->forceFill(['quantity_served' => 5])->save();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->set('goods_receipts.0.goodReceiptItems.0.quantity', 6)
        ->set('invoiceAt', now()->toDateString())
        ->set('reference_number', ['DR-1'])
        ->set('attachments', ['attachments-pdf/invoice.pdf'])
        ->call('submit')
        ->assertNotified('Could not submit');

    expect(Invoice::query()->count())->toBe(0);
});

it('requires a reference number before submitting', function () {
    $c = bulkContext();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('invoiceAt', now()->toDateString())
        ->set('reference_number', [])
        ->set('attachments', ['attachments-pdf/invoice.pdf'])
        ->call('submit')
        ->assertHasErrors(['reference_number' => 'required']);

    expect(Invoice::query()->count())->toBe(0);
});

it('requires an attachment before submitting', function () {
    $c = bulkContext();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('invoiceAt', now()->toDateString())
        ->set('reference_number', ['DR-1'])
        ->set('attachments', [])
        ->call('submit')
        ->assertHasErrors(['attachments' => 'required']);

    expect(Invoice::query()->count())->toBe(0);
});

it('escalates the selected goods receipts with a reason and remarks', function () {
    $c = bulkContext();
    $reason = bulkEscalationReason();

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->call('escalate', [
            'escalationReason' => $reason->code,
            'escalationRemarks' => 'Received quantity is short.',
        ])
        ->assertHasNoErrors();

    $split = GoodReceiptState::query()
        ->where('good_receipt_number', $c['states'][0]->good_receipt_number.'-P1')
        ->sole();

    expect($c['states'][0]->fresh()->status)->toBe(P2pStatus::Pending)
        ->and($split->status)->toBe(P2pStatus::EscalatedToWarehouse);
});

it('escalates only the ticked items and leaves the rest pending', function () {
    $c = bulkContext();
    $reason = bulkEscalationReason();

    $unselected = SapGoodReceiptItem::factory()->for($c['items'][0]->goodReceipt, 'goodReceipt')->create([
        'purchase_order' => $c['states'][0]->purchase_order,
        'purchase_order_item' => '00020',
        'quantity_po' => 5,
        'quantity_served' => 5,
        'unit_cost' => 50,
    ]);

    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->call('escalate', [
            'escalationReason' => $reason->code,
            'escalationRemarks' => 'Received quantity is short.',
        ])
        ->assertHasNoErrors();

    $split = GoodReceiptState::query()
        ->where('good_receipt_number', $c['states'][0]->good_receipt_number.'-P1')
        ->sole();

    $splitItem = SapGoodReceiptItem::query()->where('split_from_item_id', $c['items'][0]->id)->sole();

    expect($c['states'][0]->fresh()->status)->toBe(P2pStatus::Pending)
        ->and($split->status)->toBe(P2pStatus::EscalatedToWarehouse)
        ->and($c['items'][0]->fresh()->quantity_served)->toBe(0)
        ->and($unselected->fresh()->quantity_served)->toBe(5)
        ->and($unselected->fresh()->p2p_sap_good_receipt_id)->toBe($c['items'][0]->p2p_sap_good_receipt_id)
        ->and($splitItem->quantity_served)->toBe(10);
});

it('opens a non-pending goods receipt read-only', function () {
    $c = bulkContext();
    $c['states'][0]->forceFill(['status' => P2pStatus::SubmittedToAp])->save();

    $this->actingAs($c['user']);

    $component = Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]]);

    expect($component->instance()->isViewOnly)->toBeTrue();

    $component->set('reference_number', ['SHOULD-NOT-SAVE'])
        ->set('attachments', ['attachments-pdf/other.pdf'])
        ->call('submit')
        ->call('escalate', [
            'escalationReason' => 'quantity_difference',
            'escalationRemarks' => 'Should not run.',
        ]);

    expect(Invoice::query()->count())->toBe(0);
});

it('opens a submitted invoice read-only', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    $invoice = app(P2pGoodReceiptSubmissionService::class)->start($c['user'], [$c['states'][0]->id]);
    app(P2pGoodReceiptSubmissionService::class)->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['items'][0]->id, 'quantity_billed' => 10],
    ], $c['user']);

    $component = Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id]);

    $component->assertSee('Workflow Stage')
        ->assertSee('AP Processor')
        ->assertSee('AP Supervisor')
        ->assertSee(P2pStatus::SubmittedToAp->getLabel());

    expect($component->instance()->isViewOnly)->toBeTrue()
        ->and($component->instance()->goods_receipts)->toHaveCount(1)
        ->and($component->instance()->goods_receipts[0]['goodReceiptItems'])->toHaveCount(1)
        ->and($component->instance()->goods_receipts[0]['goodReceiptItems'][0]['quantity'])->toBe(10);

    // Submitting from the read-only view must not change the invoice.
    $component->set('reference_number', ['SHOULD-NOT-SAVE'])
        ->set('attachments', ['attachments-pdf/other.pdf'])
        ->call('submit');

    expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($invoice->fresh()->reference_number)->toBeNull();
});

it('re-submits a returned invoice', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    $submission = app(P2pGoodReceiptSubmissionService::class);
    $invoice = $submission->start($c['user'], [$c['states'][0]->id]);
    $invoice = $submission->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['items'][0]->id, 'quantity_billed' => 4],
    ], $c['user']);

    app(P2pGoodReceiptReturnService::class)->returnToVendor($invoice, $c['user'], 'Please correct the quantity.');

    Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->set('goods_receipts.0.goodReceiptItems.0.quantity', 10)
        ->set('reference_number', ['DR-9'])
        ->set('attachments', ['attachments-pdf/corrected.pdf'])
        ->call('submit')
        ->assertHasNoErrors();

    $invoice->refresh();

    expect($invoice->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($invoice->reference_number)->toBe(['DR-9'])
        ->and($invoice->lines()->sole()->quantity_billed)->toBe(10);
});

it('lets the vendor cancel a returned request', function () {
    Mail::fake();

    $c = bulkContext();
    $this->actingAs($c['user']);

    $submission = app(P2pGoodReceiptSubmissionService::class);
    $invoice = $submission->start($c['user'], [$c['states'][0]->id]);
    $invoice = $submission->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['items'][0]->id, 'quantity_billed' => 10],
    ], $c['user']);

    app(P2pGoodReceiptReturnService::class)->returnToVendor($invoice, $c['user'], 'Wrong invoice date.');

    Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->callAction('cancelRequest');

    expect($invoice->fresh()->status)->toBe(P2pStatus::Cancelled)
        ->and($c['states'][0]->fresh()->status)->toBe(P2pStatus::Pending);
});

it('recomputes the overall total and keeps the 3-way badge after an update', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    $component = Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true);

    expect($component->instance()->overall_invoice_total)->toBe('1,000.00')
        ->and($component->instance()->matchBase)->not->toBe([])
        ->and($component->html())->toContain('3-Way Match');

    $component->set('goods_receipts.0.goodReceiptItems.0.include', false);

    expect($component->instance()->overall_invoice_total)->toBe('0.00')
        ->and($component->instance()->matchBase)->not->toBe([])
        ->and($component->html())->toContain('3-Way Match');
});

it('hides per-line pricing and the total amount from vendors', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->assertDontSee('Unit Cost')
        ->assertDontSee('Total Amount')
        ->assertSee('Total GR Qty')
        ->assertSee('Total Invoice Qty')
        ->assertSee('Overall Invoice Total')
        ->assertSee('Click for the 3-way breakdown');
});

it('shows per-line pricing and the total amount to non-vendors', function () {
    $c = bulkContext();

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate(RoleName::Admin->value, 'web'));

    $this->actingAs($admin);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->assertSee('Unit Cost')
        ->assertSee('Amount')
        ->assertSee('Total Amount');
});

it('shows For Verification until the vendor selects the line for their invoice', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    $component = Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]]);

    expect($component->get('goods_receipts.0.goodReceiptItems.0.include'))->toBeFalse()
        ->and($component->instance()->overall_invoice_total)->toBe('0.00')
        ->and($component->html())->toContain('For Verification')->not->toContain('>Matched<');

    $component->set('goods_receipts.0.goodReceiptItems.0.include', true);

    expect($component->html())->toContain('Matched')->not->toContain('For Verification');
});

it('blocks submitting when no line is selected', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('reference_number', ['DR-1'])
        ->set('attachments', ['attachments-pdf/invoice.pdf'])
        ->call('submit')
        ->assertNotified('Select at least one line with a quantity.');

    expect(Invoice::query()->where('status', P2pStatus::SubmittedToAp)->exists())->toBeFalse();
});

it('shows the field errors instead of the confirmation modal when the invoice is not ready', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->set('goods_receipts.0.goodReceiptItems.0.quantity', 10)
        ->set('reference_number', [])
        ->set('attachments', [])
        // Ticked by hand with no invoice, so this submission would ask for confirmation
        ->callAction(TestAction::make('submit')->schemaComponent('formActions', schema: 'content'))
        ->assertHasErrors(['reference_number' => 'required', 'attachments' => 'required'])
        ->assertActionNotMounted()
        ->assertNotified('Could not submit');

    expect(Invoice::query()->count())->toBe(0);
});

it('submits flagged lines from the confirmation modal with the vendor remarks', function () {
    $c = bulkContext();
    $this->actingAs($c['user']);

    Livewire::test(BulkGoodsReceipt::class, ['ids' => [$c['states'][0]->id]])
        ->set('goods_receipts.0.goodReceiptItems.0.include', true)
        ->set('goods_receipts.0.goodReceiptItems.0.quantity', 10)
        ->set('invoiceAt', now()->toDateString())
        ->set('reference_number', ['DR-1'])
        ->set('attachments', ['attachments-pdf/invoice.pdf'])
        ->callAction(
            TestAction::make('submit')->schemaComponent('formActions', schema: 'content'),
            data: ['match_remarks' => 'Price increase approved by Purchasing'],
        )
        ->assertHasNoErrors();

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($invoice->invoice_match['vendor_remarks'])->toBe('Price increase approved by Purchasing')
        ->and($invoice->match_flag_count)->toBe(1);
});
