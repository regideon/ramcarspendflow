<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Filament\Resources\P2p\Escalations\Pages\ListEscalations;
use App\Models\Company;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\P2p\Escalation\ReverseAndReGrAction;
use App\Services\P2p\P2pEscalationRoutingService;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

/**
 * An escalated request with a two-hop route (Procurement → Warehouse) currently
 * sitting on the Procurement hop.
 *
 * @return array{invoice: Invoice, escalation: Escalation}
 */
function escalatedGrContext(): array
{
    $vendor = Vendor::factory()->create();
    $company = Company::factory()->create();

    $receipt = SapGoodReceipt::factory()->create([
        'purchase_order' => '4500009100',
        'good_receipt_number' => '5000009100',
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
    ]);

    SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => '4500009100',
        'quantity_po' => 10,
        'quantity_served' => 8,
        'unit_cost' => 100,
    ]);

    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id,
        'purchase_order' => '4500009100',
        'good_receipt_number' => '5000009100',
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => P2pStatus::EscalateToProcurement,
    ]);

    $invoice = Invoice::factory()->create([
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
        'status' => P2pStatus::EscalateToProcurement,
        'po_numbers' => ['4500009100'],
        'gr_numbers' => ['5000009100'],
        'submitted_at' => now()->subDay(),
    ]);

    $reason = EscalationReason::create([
        'code' => 'quantity_difference',
        'name' => 'Quantity Difference',
        'origin_role' => 'vendor',
        'is_active' => true,
    ]);

    $reason->steps()->create([
        'step_order' => 1,
        'role' => 'procurement',
        'status_id' => P2pStatus::EscalateToProcurement->value,
    ]);
    $reason->steps()->create([
        'step_order' => 2,
        'role' => 'warehouse',
        'status_id' => P2pStatus::EscalatedToWarehouse->value,
    ]);

    $escalation = $invoice->escalations()->create([
        'escalation_number' => 'ESC-GR-1',
        'batch_number' => 'BATCH-GR-1',
        'p2p_good_receipt_state_id' => $state->id,
        'p2p_escalation_reason_id' => $reason->id,
        'current_step_order' => 1,
        'current_role' => 'procurement',
        'origin_role' => 'vendor',
        'status' => Escalation::STATUS_OPEN,
        'from_status' => P2pStatus::Pending,
        'remarks' => 'Short shipped.',
        'escalated_at' => now(),
    ]);

    return compact('invoice', 'escalation');
}

it('lets the escalation team open the request in the bulk goods-receipt screen read-only', function () {
    ['invoice' => $invoice] = escalatedGrContext();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Procurement));

    $component = Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id]);

    expect($component->instance()->isViewOnly)->toBeTrue();
});

it('advances the escalation from the bulk goods-receipt footer', function () {
    ['invoice' => $invoice, 'escalation' => $escalation] = escalatedGrContext();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Procurement));

    Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->call('advanceOpenEscalation', ['remarks' => 'Sent to the warehouse.']);

    $escalation->refresh();

    expect($escalation->current_step_order)->toBe(2)
        ->and($escalation->current_role)->toBe('warehouse')
        ->and($escalation->status)->toBe(Escalation::STATUS_OPEN);
});

it('offers the manual reverse and re-GR fields when SAP detects no reversal', function () {
    Mail::fake();

    ['escalation' => $escalation] = escalatedGrContext();

    $escalation->reason->steps()
        ->where('step_order', 2)
        ->update(['action' => ReverseAndReGrAction::key()]);

    $procurement = User::factory()->create()->assignRole(RoleName::Procurement);
    app(P2pEscalationRoutingService::class)->advance($escalation->fresh(), $procurement, 'Sent to warehouse.');

    $action = app(ReverseAndReGrAction::class);
    $escalation->refresh();

    $fields = collect($action->schema($escalation))
        ->map(fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null)
        ->filter()
        ->values()
        ->all();

    expect($action->label($escalation))->toBe('Reverse & re-GR')
        ->and($action->modalHeading($escalation))->toBe('Reverse goods receipt & re-GR')
        ->and($fields)->toContain('reversal_document', 'replacement_gr_number', 'remarks');
});

it('returns the escalation to the previous hop from the bulk goods-receipt footer', function () {
    ['invoice' => $invoice, 'escalation' => $escalation] = escalatedGrContext();

    // Move the request onto the warehouse hop's desk.
    $escalation->forceFill(['current_step_order' => 2, 'current_role' => 'warehouse'])->save();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Warehouse));

    Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->call('returnOpenEscalation', ['remarks' => 'Back to procurement.']);

    $escalation->refresh();

    expect($escalation->current_step_order)->toBe(1)
        ->and($escalation->current_role)->toBe('procurement');
});

it('refuses an escalation action from a user who does not own the hop', function () {
    ['invoice' => $invoice, 'escalation' => $escalation] = escalatedGrContext();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Warehouse));

    Livewire::test(BulkGoodsReceipt::class, ['invoice' => $invoice->id])
        ->call('advanceOpenEscalation', ['remarks' => 'Not mine.']);

    $escalation->refresh();

    expect($escalation->current_step_order)->toBe(1)
        ->and($escalation->current_role)->toBe('procurement');
});

it('scopes For My Action to the current hop and In Progress to other hops', function () {
    ['invoice' => $invoice, 'escalation' => $procurement] = escalatedGrContext();

    $warehouse = $invoice->escalations()->create([
        'escalation_number' => 'ESC-GR-2',
        'batch_number' => 'BATCH-GR-2',
        'p2p_good_receipt_state_id' => $procurement->p2p_good_receipt_state_id,
        'p2p_escalation_reason_id' => $procurement->p2p_escalation_reason_id,
        'current_step_order' => 2,
        'current_role' => 'warehouse',
        'origin_role' => 'vendor',
        'status' => Escalation::STATUS_OPEN,
        'from_status' => P2pStatus::Pending,
        'remarks' => 'With the warehouse.',
        'escalated_at' => now(),
    ]);

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Procurement));

    Livewire::withQueryParams(['tab' => 'for_my_action'])
        ->test(ListEscalations::class)
        ->assertCanSeeTableRecords([$procurement])
        ->assertCanNotSeeTableRecords([$warehouse]);

    Livewire::withQueryParams(['tab' => 'in_progress'])
        ->test(ListEscalations::class)
        ->assertCanSeeTableRecords([$warehouse])
        ->assertCanNotSeeTableRecords([$procurement]);
});

it('links the escalations list View action to the bulk goods-receipt screen', function () {
    ['invoice' => $invoice, 'escalation' => $escalation] = escalatedGrContext();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Procurement));

    Livewire::test(ListEscalations::class)
        ->assertTableActionHasUrl('view', BulkGoodsReceipt::getUrl(['invoice' => $invoice->id]), $escalation);
});
