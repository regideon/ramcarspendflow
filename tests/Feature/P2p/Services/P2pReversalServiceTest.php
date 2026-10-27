<?php

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Contracts\P2p\SapReversalSource;
use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\Escalation\ReverseAndReGrAction;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptReversalService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use App\Services\P2p\P2pReversalDetectionService;
use App\Support\GoodReceiptNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

pest()->use(RefreshDatabase::class);

/**
 * @return array{po: SapPurchaseOrder, receipt: SapGoodReceipt, state: GoodReceiptState}
 */
function reversalContext(): array
{
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000400']);
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

    $splitState = GoodReceiptState::query()
        ->where('good_receipt_number', $invoice->gr_numbers[0])
        ->sole();

    return compact('po', 'receipt', 'state', 'splitState', 'invoice');
}

it('marks a GR reversed and links the replacement', function () {
    $c = reversalContext();

    app(P2pGoodReceiptReversalService::class)->apply($c['splitState'], '4900000001', '5000000009-R');

    $state = $c['splitState']->fresh();

    expect($state->reversed_at)->not->toBeNull()
        ->and($state->reversal_document)->toBe('4900000001')
        ->and($state->replacement_gr_number)->toBe('5000000009-R')
        ->and($state->status)->toBe(P2pStatus::Reversed)
        ->and($c['po']->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::PartiallySubmitted);
});

it('detects and applies reversals from a sync payload', function () {
    $c = reversalContext();

    $applied = app(P2pReversalDetectionService::class)->detect(
        [$c['receipt']->good_receipt_number => '4900000002'],
        [$c['receipt']->good_receipt_number => '5000000009-R'],
    );

    expect($applied)->toBe(1)
        ->and($c['splitState']->fresh()->status)->toBe(P2pStatus::Reversed);

    // Already reversed: a second pass is a no-op.
    expect(app(P2pReversalDetectionService::class)->detect([$c['receipt']->good_receipt_number => '4900000002']))->toBe(0);
});

it('detects a reversal and moves the request onto the replacement re-GR', function () {
    Mail::fake();

    $c = reversalContext();
    $base = GoodReceiptNumber::base($c['splitState']->good_receipt_number);

    $source = new class($base) implements SapPurchaseOrderSource, SapReversalSource
    {
        public function __construct(private string $base) {}

        public function changedPurchaseOrders(array $filters = []): array
        {
            return [];
        }

        public function reversalsForPurchaseOrder(string $purchaseOrder): array
        {
            return [
                'reversed' => [$this->base => '4900000004'],
                'replacements' => [$this->base => '5000000010-R'],
            ];
        }
    };

    app()->instance(SapPurchaseOrderSource::class, $source);

    $reason = EscalationReason::create([
        'code' => 'reverse_and_regr',
        'name' => 'Wrong delivery',
        'origin_role' => 'vendor',
        'is_active' => true,
    ]);
    $reason->steps()->create([
        'step_order' => 1,
        'role' => 'warehouse',
        'status_id' => P2pStatus::EscalatedToWarehouse->value,
        'action' => ReverseAndReGrAction::key(),
    ]);
    $reason->steps()->create([
        'step_order' => 2,
        'role' => 'vendor',
        'status_id' => P2pStatus::ReturnedToVendor->value,
        'override_header_status' => true,
    ]);

    $user = User::factory()->create();
    $routing = app(P2pEscalationRoutingService::class);
    $escalation = $routing->start($c['invoice'], $reason->code, [$c['splitState']->id], $user, 'Wrong item')->first();

    $routing->advance($escalation->fresh(), $user, 'Reverse and re-GR.');

    expect($c['splitState']->fresh()->reversed_at)->not->toBeNull()
        ->and($c['splitState']->fresh()->reversal_document)->toBe('4900000004')
        ->and($c['invoice']->fresh()->gr_numbers)->toBe(['5000000010-R']);
});
