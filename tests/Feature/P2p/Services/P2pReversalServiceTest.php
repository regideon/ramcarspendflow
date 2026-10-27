<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\P2pGoodReceiptReversalService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use App\Services\P2p\P2pReversalDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    return compact('po', 'receipt', 'state', 'splitState');
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
