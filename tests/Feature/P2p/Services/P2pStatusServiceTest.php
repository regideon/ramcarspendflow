<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Services\P2p\P2pGoodReceiptStatusService;
use App\Services\P2p\P2pPurchaseOrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

function makeGrState(SapPurchaseOrder $po, P2pStatus $status, string $gr): GoodReceiptState
{
    return GoodReceiptState::create([
        'purchase_order' => $po->purchase_order,
        'good_receipt_number' => $gr,
        'status' => $status,
    ]);
}

it('derives the PO status from its GR states', function (array $statuses, P2pPurchaseOrderStatus $expected) {
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000001']);

    foreach ($statuses as $index => $status) {
        makeGrState($po, $status, '500000000'.($index + 1));
    }

    expect(app(P2pPurchaseOrderStatusService::class)->derive($po))->toBe($expected);
})->with([
    'all pending' => [[P2pStatus::Pending, P2pStatus::Pending], P2pPurchaseOrderStatus::Pending],
    'one submitted' => [[P2pStatus::Pending, P2pStatus::SubmittedToAp], P2pPurchaseOrderStatus::PartiallySubmitted],
    'all submitted' => [[P2pStatus::SubmittedToAp, P2pStatus::SubmittedToChecker], P2pPurchaseOrderStatus::FullySubmitted],
    'all parked' => [[P2pStatus::InvoiceParked, P2pStatus::InvoiceParked], P2pPurchaseOrderStatus::Completed],
    'one returned' => [[P2pStatus::SubmittedToAp, P2pStatus::ReturnedToVendor], P2pPurchaseOrderStatus::PartiallySubmitted],
    'escalated counts as submitted' => [[P2pStatus::SubmittedToAp, P2pStatus::EscalatedToWarehouse], P2pPurchaseOrderStatus::FullySubmitted],
]);

it('persists the recalculated PO status', function () {
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000002', 'status_id' => P2pPurchaseOrderStatus::Pending]);
    makeGrState($po, P2pStatus::SubmittedToAp, '5000000011');

    app(P2pPurchaseOrderStatusService::class)->recalculate($po);

    expect($po->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::FullySubmitted);
});

it('defaults a GR to pending with no invoice and no escalation', function () {
    $state = GoodReceiptState::create(['good_receipt_number' => '5000000010', 'status' => P2pStatus::Pending]);

    expect(app(P2pGoodReceiptStatusService::class)->derive($state))->toBe(P2pStatus::Pending);
});

it('derives a GR status from the invoice that bills it', function () {
    $receipt = SapGoodReceipt::factory()->create();
    $item = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create();
    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id,
        'good_receipt_number' => $receipt->good_receipt_number,
        'status' => P2pStatus::Pending,
    ]);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToAp]);
    InvoiceLine::factory()->for($invoice, 'invoice')->create(['p2p_sap_good_receipt_item_id' => $item->id]);

    expect(app(P2pGoodReceiptStatusService::class)->derive($state))->toBe(P2pStatus::SubmittedToAp);
});

it('marks a reversed GR as reversed regardless of invoices', function () {
    $state = GoodReceiptState::create([
        'good_receipt_number' => '5000000012',
        'status' => P2pStatus::SubmittedToAp,
        'reversed_at' => now(),
    ]);

    expect(app(P2pGoodReceiptStatusService::class)->derive($state))->toBe(P2pStatus::Reversed);
});

it('lets an open escalation own the GR status', function () {
    $invoice = Invoice::factory()->create();
    $reason = EscalationReason::create(['code' => 'quantity_difference', 'name' => 'Quantity Difference', 'origin_role' => 'vendor']);
    $reason->steps()->create(['step_order' => 1, 'role' => 'warehouse', 'status_id' => P2pStatus::EscalatedToWarehouse->value]);

    $state = GoodReceiptState::create(['good_receipt_number' => '5000000013', 'status' => P2pStatus::Pending]);

    Escalation::create([
        'escalation_number' => 'ESC-20261010-000001',
        'p2p_invoice_id' => $invoice->id,
        'p2p_good_receipt_state_id' => $state->id,
        'p2p_escalation_reason_id' => $reason->id,
        'current_step_order' => 1,
        'current_role' => 'warehouse',
        'status' => 'open',
    ]);

    expect(app(P2pGoodReceiptStatusService::class)->recalculate($state))->toBe(P2pStatus::EscalatedToWarehouse);
});
