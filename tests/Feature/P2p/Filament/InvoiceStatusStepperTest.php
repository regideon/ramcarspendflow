<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

/**
 * Render the stepper component for an invoice status.
 */
function renderStepper(P2pStatus $status): string
{
    return view('filament.components.p2p-status-stepper', ['status' => $status])->render();
}

it('renders the forward path with the current status highlighted', function () {
    $html = renderStepper(P2pStatus::SubmittedToAp);

    // The "Workflow Stage" heading and the status badge come from the page's section
    expect($html)->toContain('Vendor')
        ->and($html)->toContain('AP Processor')
        ->and($html)->toContain('AP Supervisor')
        ->and($html)->not->toContain('Checker')
        ->and($html)->toContain('SAP')
        ->and($html)->toContain('Completed')
        ->and($html)->toContain('var(--primary-600)'); // current step in the brand colour
});

it('does not mark the terminal node on a normal in-progress invoice', function () {
    $html = renderStepper(P2pStatus::SubmittedToChecker);
    $terminal = renderStepper(P2pStatus::Rejected);

    // The terminal "!" node only renders for dead-end statuses (rejected/cancelled/
    // reversed); a normal in-progress invoice should not carry one.
    $compact = fn (string $markup): string => preg_replace('/\s+/', '', $markup);

    expect($compact($html))->not->toContain('>!</div>')
        ->and($compact($terminal))->toContain('>!</div>');
});

it('lights visited steps from the approval history', function () {
    $invoice = Invoice::factory()->create(['status' => P2pStatus::ReturnedToVendor]);

    ApprovalHistory::create([
        'p2p_invoice_id' => $invoice->id,
        'from_status' => P2pStatus::SubmittedToAp,
        'to_status' => P2pStatus::SubmittedToChecker,
    ]);

    $html = view('filament.components.p2p-status-stepper', [
        'status' => $invoice->status,
        'histories' => $invoice->histories()->orderBy('created_at')->get(),
    ])->render();

    // Returned to vendor: the current marker is Vendor, but the trail still shows
    // the steps it reached before coming back (Checker = index 2 => Vendor+Processor done).
    expect(substr_count($html, 'M16.704 5.29'))->toBe(2) // Vendor and AP Processor ticked
        ->and($html)->toContain('Vendor');
});

it('renders a terminal node for a rejected invoice', function () {
    $html = renderStepper(P2pStatus::Rejected);

    expect($html)->toContain(P2pStatus::Rejected->getLabel());
});

it('renders escalation hop labels per batch', function () {
    $reason = EscalationReason::create([
        'code' => 'quantity_difference',
        'name' => 'Quantity Difference',
        'origin_role' => 'vendor',
        'is_active' => true,
    ]);
    $reason->steps()->create(['step_order' => 1, 'role' => 'procurement', 'status_id' => P2pStatus::EscalateToProcurement->value]);
    $reason->steps()->create(['step_order' => 2, 'role' => 'warehouse', 'status_id' => P2pStatus::EscalatedToWarehouse->value]);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::EscalateToProcurement]);
    $state = GoodReceiptState::create([
        'purchase_order' => '4500008800',
        'good_receipt_number' => '5000008800',
        'company_id' => $invoice->company_id,
        'vendor_id' => $invoice->vendor_id,
        'status' => P2pStatus::EscalateToProcurement,
    ]);

    Escalation::create([
        'escalation_number' => 'ESC-STEPPER-1',
        'batch_number' => 'BATCH-STEPPER-1',
        'p2p_invoice_id' => $invoice->id,
        'p2p_good_receipt_state_id' => $state->id,
        'p2p_escalation_reason_id' => $reason->id,
        'current_step_order' => 1,
        'current_role' => 'procurement',
        'origin_role' => 'vendor',
        'status' => Escalation::STATUS_OPEN,
        'escalated_at' => now(),
    ]);

    $html = view('filament.components.p2p-status-stepper', [
        'status' => $invoice->status,
        'escalations' => $invoice->escalations()->with('reason.steps')->get(),
    ])->render();

    expect($html)->toContain('Escalation')
        ->and($html)->toContain('BATCH-STEPPER-1')
        ->and($html)->toContain('Procurement')
        ->and($html)->toContain('Warehouse')
        // The escalation route replaces the normal forward path, so its steps
        // (Processor / Checker / Completed) are no longer rendered.
        ->and($html)->not->toContain('Completed')
        ->and($html)->not->toContain('Processor')
        ->and($html)->not->toContain('Checker');
});

it('renders the stepper inside the invoice view page', function () {
    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToAp]);
    $user = User::factory()->create()->assignRole(RoleName::Admin);

    $this->actingAs($user);

    Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->assertOk()
        ->assertSee('Workflow Stage');
});
