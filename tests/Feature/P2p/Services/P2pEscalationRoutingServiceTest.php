<?php

use App\Enums\P2pStatus;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\Escalation\ReverseAndReGrAction;
use App\Services\P2p\Escalation\StepAction;
use App\Services\P2p\Escalation\StepActionCompletion;
use App\Services\P2p\Escalation\StepActionContext;
use App\Services\P2p\Escalation\StepActionRegistry;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

/**
 * @return array{invoice: Invoice, state: GoodReceiptState, user: User}
 */
function escalationContext(): array
{
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000300']);
    $receipt = SapGoodReceipt::factory()->create(['purchase_order' => $po->purchase_order]);
    $item = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => $po->purchase_order,
        'quantity_po' => 10,
        'quantity_served' => 10,
        'unit_cost' => 100,
    ]);
    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id,
        'purchase_order' => $po->purchase_order,
        'good_receipt_number' => $receipt->good_receipt_number,
        'company_id' => $receipt->company_id,
        'vendor_id' => $receipt->vendor_id,
        'status' => P2pStatus::Pending,
    ]);
    $user = User::factory()->create();

    $submission = app(P2pGoodReceiptSubmissionService::class);
    $invoice = $submission->start($user, [$state->id]);
    $invoice = $submission->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $item->id, 'quantity_billed' => 10],
    ], $user);

    return compact('invoice', 'state', 'user');
}

function quantityEscalationReason(): EscalationReason
{
    $reason = EscalationReason::create([
        'code' => 'quantity_difference',
        'name' => 'Quantity Difference',
        'origin_role' => 'vendor',
        'is_active' => true,
    ]);

    $reason->steps()->create(['step_order' => 1, 'role' => 'warehouse', 'status_id' => P2pStatus::EscalatedToWarehouse->value]);
    $reason->steps()->create(['step_order' => 2, 'role' => 'vendor', 'status_id' => P2pStatus::ReturnedToVendor->value, 'override_header_status' => true]);

    return $reason;
}

it('starts one escalation per GR and moves the GR and invoice to the first hop', function () {
    $c = escalationContext();
    quantityEscalationReason();

    $escalations = app(P2pEscalationRoutingService::class)->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Quantity is short');

    $escalation = $escalations->first();

    expect($escalations)->toHaveCount(1)
        ->and($escalation->current_step_order)->toBe(1)
        ->and($escalation->current_role)->toBe('warehouse')
        ->and($escalation->status)->toBe('open')
        ->and($escalation->batch_number)->not->toBeNull()
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::EscalatedToWarehouse)
        ->and($c['invoice']->fresh()->status)->toBe(P2pStatus::EscalatedToWarehouse);
});

it('requires remarks to start an escalation', function () {
    $c = escalationContext();
    quantityEscalationReason();

    expect(fn () => app(P2pEscalationRoutingService::class)->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], '  '))
        ->toThrow(ValidationException::class);
});

it('advances through the hops', function () {
    $c = escalationContext();
    quantityEscalationReason();
    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    $service->advance($escalation->fresh(), $c['user']);

    expect($escalation->fresh()->current_step_order)->toBe(2)
        ->and($escalation->fresh()->current_role)->toBe('vendor')
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::ReturnedToVendor)
        ->and($c['invoice']->fresh()->status)->toBe(P2pStatus::ReturnedToVendor);
});

it('resolves at the terminal hop and restores the previous status', function () {
    $c = escalationContext();
    quantityEscalationReason();
    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    $service->advance($escalation->fresh(), $c['user']);
    $service->advance($escalation->fresh(), $c['user']); // terminal → resolve

    expect($escalation->fresh()->status)->toBe('resolved')
        ->and($escalation->fresh()->resolved_at)->not->toBeNull()
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($c['invoice']->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});

it('registers the reverse & re-GR step action', function () {
    expect(app(StepActionRegistry::class)->has(ReverseAndReGrAction::key()))->toBeTrue();
});

it('resolves through a custom hop action configured on the route', function () {
    $c = escalationContext();
    $reason = quantityEscalationReason();
    $reason->steps()->where('step_order', 1)->update([
        'action' => ReverseAndReGrAction::key(),
        'action_label' => 'Reverse & re-GR',
    ]);

    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    $service->advance($escalation->fresh(), $c['user']);

    expect($escalation->fresh()->status)->toBe('resolved')
        ->and($escalation->fresh()->current_step_order)->toBe(1)
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});

it('keeps the escalation on its hop when a custom action declines to act', function () {
    $c = escalationContext();
    $reason = quantityEscalationReason();
    $reason->steps()->where('step_order', 1)->update(['action' => 'does_nothing']);

    app(StepActionRegistry::class)->register('does_nothing', new class implements StepAction
    {
        public function handle(StepActionContext $context): StepActionCompletion
        {
            return StepActionCompletion::skipped('Nothing to do.');
        }
    });

    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    $service->advance($escalation->fresh(), $c['user']);

    expect($escalation->fresh()->status)->toBe('open')
        ->and($escalation->fresh()->current_step_order)->toBe(1)
        ->and($escalation->fresh()->current_role)->toBe('warehouse');
});

it('resolves at a terminal hop when its team acts on it', function () {
    $c = escalationContext();
    quantityEscalationReason();
    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    // Move onto the terminal (vendor-return) hop...
    $service->advance($escalation->fresh(), $c['user']);

    expect($escalation->fresh()->status)->toBe('open')
        ->and($escalation->fresh()->current_role)->toBe('vendor');

    // ...then the hop's team acts, which closes the cycle.
    $service->advance($escalation->fresh(), $c['user']);

    expect($escalation->fresh()->status)->toBe('resolved')
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});

it('labels the hop action from the route and falls back to the previous hop role', function () {
    $c = escalationContext();
    $reason = quantityEscalationReason();
    $reason->steps()->where('step_order', 1)->update([
        'action' => ReverseAndReGrAction::key(),
        'action_label' => 'Reverse & re-GR',
    ]);

    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    expect($escalation->currentStepAction())->toBeInstanceOf(ReverseAndReGrAction::class)
        ->and($escalation->currentActionLabel())->toBe('Reverse & re-GR')
        ->and($escalation->previousRoleLabel())->toBe('Vendor');
});

it('treats the hop whose destination is terminal as the last relay', function () {
    $c = escalationContext();
    quantityEscalationReason();
    $service = app(P2pEscalationRoutingService::class);
    $escalation = $service->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short')->first();

    // At the warehouse hop, the destination (step 2, vendor return) is terminal.
    expect($escalation->nextStep()?->override_header_status)->toBeTrue();

    // Acting at the warehouse hop lands on that terminal destination.
    $service->advance($escalation->fresh(), $c['user']);

    expect($escalation->fresh()->status)->toBe('open')
        ->and($escalation->fresh()->current_role)->toBe('vendor')
        // The vendor hop is the final one: nothing follows it.
        ->and($escalation->fresh()->nextStep())->toBeNull();
});
