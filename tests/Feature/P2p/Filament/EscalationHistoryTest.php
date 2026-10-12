<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Escalations\Pages\ListEscalations;
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

function escalationTimelineContext(): array
{
    $actor = User::factory()->create()->assignRole(RoleName::Procurement);
    $reason = EscalationReason::create([
        'code' => 'quantity_difference',
        'name' => 'Quantity Difference',
        'origin_role' => 'vendor',
        'is_active' => true,
    ]);
    $reason->steps()->create(['step_order' => 1, 'role' => 'procurement', 'status_id' => P2pStatus::EscalateToProcurement->value]);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::EscalateToProcurement]);
    $state = GoodReceiptState::create([
        'purchase_order' => '4500007700',
        'good_receipt_number' => '5000007700',
        'company_id' => $invoice->company_id,
        'vendor_id' => $invoice->vendor_id,
        'status' => P2pStatus::EscalateToProcurement,
    ]);

    $escalation = Escalation::create([
        'escalation_number' => 'ESC-TEST-1',
        'batch_number' => 'BATCH-TEST-1',
        'p2p_invoice_id' => $invoice->id,
        'p2p_good_receipt_state_id' => $state->id,
        'p2p_escalation_reason_id' => $reason->id,
        'current_step_order' => 1,
        'current_role' => 'procurement',
        'origin_role' => 'vendor',
        'status' => Escalation::STATUS_OPEN,
        'escalated_at' => now(),
    ]);

    return [$actor, $escalation, $invoice];
}

it('renders the approval history timeline with escalation details', function () {
    [$actor, $escalation, $invoice] = escalationTimelineContext();

    ApprovalHistory::create([
        'p2p_invoice_id' => $invoice->id,
        'actioned_by' => $actor->id,
        'to_status' => P2pStatus::EscalateToProcurement,
        'remarks' => 'Quantity is short by 2 units.',
        'escalation_reason' => 'Quantity Difference',
        'escalated_items' => ['5000007700'],
        'is_visible_to_vendor' => true,
        'is_visible_to_admin' => true,
    ]);

    $html = view('filament.components.approval-history-timeline', ['record' => $invoice->fresh()])->render();

    expect($html)->toContain('Quantity is short by 2 units.')
        ->and($html)->toContain('Quantity Difference')
        ->and($html)->toContain('5000007700')
        ->and($html)->toContain('Escalated by');
});

it('shows an empty state when there is no history', function () {
    [, , $invoice] = escalationTimelineContext();

    $html = view('filament.components.approval-history-timeline', ['record' => $invoice->fresh()])->render();

    expect($html)->toContain('No approval history available');
});

it('hides vendor-invisible remarks from the vendor', function () {
    [$actor, , $invoice] = escalationTimelineContext();

    ApprovalHistory::create([
        'p2p_invoice_id' => $invoice->id,
        'actioned_by' => $actor->id,
        'to_status' => P2pStatus::EscalateToProcurement,
        'remarks' => 'Internal warehouse note.',
        'is_visible_to_vendor' => false,
        'is_visible_to_admin' => true,
    ]);

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor));

    $html = view('filament.components.approval-history-timeline', ['record' => $invoice->fresh()])->render();

    expect($html)->not->toContain('Internal warehouse note.');
});

it('exposes the history action on the escalations list', function () {
    [$actor, $escalation] = escalationTimelineContext();

    $this->actingAs($actor);

    Livewire::test(ListEscalations::class)->assertTableActionExists('history');
});

it('withholds pricing from the vendor summarize footer', function () {
    $items = [
        ['quantity_po' => 10, 'quantity_served' => 8, 'quantity' => 8, 'unit_cost' => 100],
    ];

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor));
    $vendorHtml = view('filament.components.good-receipt-summarize', ['items' => $items])->render();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));
    $staffHtml = view('filament.components.good-receipt-summarize', ['items' => $items])->render();

    expect($vendorHtml)->toContain('Total GR Qty')
        ->and($vendorHtml)->not->toContain('Total Amount')
        ->and($staffHtml)->toContain('Total Amount');
});

it('links the uploaded pdf from the open-pdf component', function () {
    $html = view('filament.components.open-pdf-link', [
        'attachments' => ['attachments-pdf/invoice.pdf'],
    ])->render();

    expect($html)->toContain('View File Uploaded')
        ->and($html)->toContain('invoice.pdf');

    $empty = view('filament.components.open-pdf-link', ['attachments' => []])->render();

    expect($empty)->toContain('No file uploaded.');
});
