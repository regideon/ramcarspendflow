<?php

use App\Enums\P2pStatus;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Filament\Resources\P2p\Approvals\Pages\ListApprovals;
use App\Models\Company;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\Rfp\Rfp;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Services\P2p\P2pInvoiceParkingService;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
        ->assertSee('Items')
        ->assertSee('5000006000');
});

it('requires a tax code before submitting to the checker', function () {
    [$processor, $invoice] = apPageContext();
    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->set('data.tax_code', null)
        ->call('submit');

    // The handler refuses and leaves the submission untouched.
    expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});

it('moves the submission to the checker once a tax code is set', function () {
    [$processor, $invoice] = apPageContext();
    $this->actingAs($processor);

    TaxCode::factory()->create(['code' => 'I2', 'description' => 'Input VAT 12%', 'rate' => 12]);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->set('data.tax_code', 'I2')
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
    $goodsReceipts = $component->get('data.goods_receipts');
    $goodsReceipts[0]['goodReceiptItems'][0]['escalate'] = true;
    $component->set('data.goods_receipts', $goodsReceipts);

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

it('offers the supervisor Approve and Return instead of the processor actions', function () {
    [$supervisor, $invoice] = apPageContext(P2pStatus::SubmittedToChecker);
    $this->actingAs($supervisor);

    // The fixture user is a processor; swap in a supervisor.
    $actualSupervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    $this->actingAs($actualSupervisor);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id]);

    $actions = collect($component->instance()->getFooterActions())
        ->map(fn ($action): string => $action->getName())
        ->all();

    expect($actions)->toContain('approve', 'returnToApProcessor')
        ->and($actions)->not->toContain('submit', 'escalate', 'returnToVendor');
});

it('parks the invoice when the supervisor approves', function () {
    $supervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    [, $invoice] = apPageContext(P2pStatus::SubmittedToChecker);
    $this->actingAs($supervisor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('approve', ['remarks_checker' => 'Checked against the PO.']);

    $invoice->refresh();

    expect($invoice->status)->toBe(P2pStatus::PendingPost)
        ->and($invoice->processed)->toBeTrue();

    // The intended SAP payload is logged for the eventual integration.
    $this->assertDatabaseHas('p2p_sap_api_logs', [
        'p2p_invoice_id' => $invoice->id,
        'endpoint' => 'park_invoice',
    ]);

    // And the decision is in the audit trail.
    $this->assertDatabaseHas('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::PendingPost->value,
    ]);
});

it('returns the submission to the processor when the supervisor declines', function () {
    $supervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    [, $invoice] = apPageContext(P2pStatus::SubmittedToChecker);
    $this->actingAs($supervisor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('returnToApProcessor', ['remarks_checker' => 'Tax code is wrong.']);

    expect($invoice->fresh()->status)->toBe(P2pStatus::ReturnedToApProcessor);
});

it('refuses to park an invoice that is not awaiting the checker', function () {
    $supervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    [, $invoice] = apPageContext(P2pStatus::SubmittedToAp);
    $this->actingAs($supervisor);

    expect(fn () => app(P2pInvoiceParkingService::class)
        ->park($invoice, $supervisor, 'Premature.'))
        ->toThrow(ValidationException::class);

    expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});

/**
 * Escalate the fixture's GR to procurement, so the footer is owned by the hop.
 */
function escalateFixtureToProcurement(Invoice $invoice, GoodReceiptState $state, User $by): Escalation
{
    $reason = EscalationReason::create([
        'code' => 'qty_issue',
        'name' => 'Quantity Issue',
        'origin_role' => 'processor',
        'is_active' => true,
    ]);

    $reason->steps()->create([
        'step_order' => 1,
        'role' => 'procurement',
        'status_id' => P2pStatus::EscalateToProcurement->value,
    ]);

    return $invoice->escalations()->create([
        'escalation_number' => 'ESC-TEST',
        'batch_number' => 'BATCH-TEST',
        'p2p_good_receipt_state_id' => $state->id,
        'p2p_escalation_reason_id' => $reason->id,
        'current_step_order' => 1,
        'current_role' => 'procurement',
        'origin_role' => $reason->origin_role,
        'status' => Escalation::STATUS_OPEN,
        'from_status' => $state->status,
        'remarks' => 'Numbers do not agree.',
        'actioned_by' => $by->id,
        'escalated_at' => now(),
    ]);
}

it('hands the footer to Advance and Return when an escalation waits on the user', function () {
    [$processor, $invoice, $state] = apPageContext();
    escalateFixtureToProcurement($invoice, $state, $processor);

    $procurement = User::factory()->create()->assignRole(RoleName::Procurement);
    $this->actingAs($procurement);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id]);

    $actions = collect($component->instance()->getFooterActions())
        ->map(fn ($action): string => $action->getName())
        ->all();

    expect($actions)->toContain('advanceEscalation', 'returnEscalation', 'back')
        ->and($actions)->not->toContain('submit', 'escalate', 'returnToVendor', 'approve');
});

it('keeps the normal footer when the escalation is not on the user', function () {
    [$processor, $invoice, $state] = apPageContext();
    escalateFixtureToProcurement($invoice, $state, $processor);

    // The processor raised it; the hop now belongs to procurement.
    $this->actingAs($processor);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id]);

    $actions = collect($component->instance()->getFooterActions())
        ->map(fn ($action): string => $action->getName())
        ->all();

    expect($actions)->toContain('submit', 'escalate', 'returnToVendor')
        ->and($actions)->not->toContain('advanceEscalation');
});

it('advances an escalation from the AP footer', function () {
    [$processor, $invoice, $state] = apPageContext();
    $escalation = escalateFixtureToProcurement($invoice, $state, $processor);

    $procurement = User::factory()->create()->assignRole(RoleName::Procurement);
    $this->actingAs($procurement);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->callAction('advanceEscalation', ['remarks' => 'Sent to the warehouse.']);

    // The single-hop route is terminal, so advancing resolves it.
    expect($escalation->fresh()->status)->toBe(Escalation::STATUS_RESOLVED);
});

it('refuses an escalation action from a user who does not own the hop', function () {
    [$processor, $invoice, $state] = apPageContext();
    $escalation = escalateFixtureToProcurement($invoice, $state, $processor);

    // The processor is the originator, not the current-hop owner.
    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->call('advanceOpenEscalation', ['remarks' => 'Trying anyway.']);

    expect($escalation->fresh()->status)->toBe(Escalation::STATUS_OPEN);
});

it('opens a submission outside the viewer queue read-only', function () {
    [$processor, $invoice] = apPageContext(P2pStatus::SubmittedToChecker);
    $this->actingAs($processor);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id]);

    expect($component->instance()->isViewOnly)->toBeTrue();
});

it('opens the processor queue read-only for a supervisor', function () {
    $supervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    [, $invoice] = apPageContext(P2pStatus::SubmittedToAp);
    $this->actingAs($supervisor);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id]);

    expect($component->instance()->isViewOnly)->toBeTrue();

    $actions = collect($component->instance()->getFooterActions())
        ->map(fn ($action): string => $action->getName())
        ->all();

    expect($actions)->not->toContain('approve', 'returnToApProcessor');
});

/**
 * @return array{0: Rfp, 1: Rfp, 2: Rfp}
 */
function vendorRfps(Invoice $invoice): array
{
    $paid = Rfp::factory()->create(['vendor_id' => $invoice->vendor_id, 'status' => RfpStatus::Completed, 'total_php_amount' => '300.00']);
    $inProgress = Rfp::factory()->create(['vendor_id' => $invoice->vendor_id, 'status' => RfpStatus::ForApproval]);
    $otherVendor = Rfp::factory()->create(['status' => RfpStatus::Completed]);

    return [$paid, $inProgress, $otherVendor];
}

it('links only paid RFPs of the same vendor to the invoice', function () {
    [$processor, $invoice] = apPageContext();
    [$paid, $inProgress, $otherVendor] = vendorRfps($invoice);

    $invoice->syncLinkedRfps([$paid->id, $inProgress->id, $otherVendor->id], $processor);

    expect($invoice->linkedRfps()->pluck('rfps.id')->all())->toBe([$paid->id])
        ->and($invoice->linkedRfps()->first()->pivot->linked_by_id)->toBe($processor->id);
});

it('lets the AP Processor link RFPs from the page, for reference only', function () {
    [$processor, $invoice] = apPageContext();
    [$paid, , $otherVendor] = vendorRfps($invoice);
    $amountBefore = $invoice->amount_submitted;
    $this->actingAs($processor);

    $component = Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->assertSee('RFP Down Payments')
        ->assertFormFieldExists('linked_rfp_ids', fn ($field): bool => array_keys($field->getOptions()) === [$paid->id])
        ->set('data.linked_rfp_ids', [(string) $paid->id])
        ->assertNotified('Linked RFPs saved')
        ->assertSee($paid->rfp_number);

    expect($invoice->fresh()->linkedRfps->pluck('id')->all())->toBe([$paid->id])
        ->and($invoice->fresh()->amount_submitted)->toBe($amountBefore);
});

it('shows linked RFPs read-only outside the AP Processor queue', function () {
    [, $invoice] = apPageContext(P2pStatus::SubmittedToChecker);
    [$paid] = vendorRfps($invoice);
    $invoice->linkedRfps()->attach($paid->id);
    $supervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    $this->actingAs($supervisor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->assertSee('RFP Down Payments')
        ->assertSee($paid->rfp_number)
        ->assertFormFieldHidden('linked_rfp_ids');
});

it('lists the linked RFP down payments on the Approvals queue', function () {
    [$processor, $invoice] = apPageContext();
    [$paid] = vendorRfps($invoice);
    $invoice->linkedRfps()->attach($paid->id);
    $this->actingAs($processor);

    Livewire::test(ListApprovals::class)
        ->assertTableColumnStateSet('rfp_down_payment', [$paid->rfp_number], $invoice);
});

it('lets a user who also holds AP Supervisor link RFPs while the invoice is with the AP Processor', function () {
    [, $invoice] = apPageContext();
    [$paid] = vendorRfps($invoice);
    $multiRole = User::factory()->create();
    $multiRole->assignRole([RoleName::Admin, RoleName::ApProcessor, RoleName::ApSupervisor]);
    $this->actingAs($multiRole);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->assertFormFieldVisible('linked_rfp_ids')
        ->set('data.linked_rfp_ids', [(string) $paid->id])
        ->assertNotified('Linked RFPs saved');

    expect($invoice->fresh()->linkedRfps->pluck('id')->all())->toBe([$paid->id]);
});
