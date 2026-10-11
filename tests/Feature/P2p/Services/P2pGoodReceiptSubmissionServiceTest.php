<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

/**
 * @return array{po: SapPurchaseOrder, receipt: SapGoodReceipt, item: SapGoodReceiptItem, state: GoodReceiptState, user: User}
 */
function submissionContext(float $quantity = 10, float $unitCost = 100): array
{
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000100']);
    $receipt = SapGoodReceipt::factory()->create(['purchase_order' => $po->purchase_order]);
    $item = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => $po->purchase_order,
        'quantity_po' => $quantity,
        'quantity_served' => $quantity,
        'unit_cost' => $unitCost,
    ]);
    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id,
        'purchase_order' => $po->purchase_order,
        'good_receipt_number' => $receipt->good_receipt_number,
        'company_id' => $receipt->company_id,
        'vendor_id' => $receipt->vendor_id,
        'status' => P2pStatus::Pending,
    ]);

    return compact('po', 'receipt', 'item', 'state') + ['user' => User::factory()->create()];
}

it('opens a draft submission for a pending GR', function () {
    $c = submissionContext();

    $invoice = app(P2pGoodReceiptSubmissionService::class)->start($c['user'], [$c['state']->id]);

    expect($invoice->status)->toBe(P2pStatus::Pending)
        ->and($invoice->gr_numbers)->toBe([$c['receipt']->good_receipt_number])
        ->and($invoice->po_numbers)->toBe([$c['po']->purchase_order])
        ->and($invoice->user->is($c['user']))->toBeTrue();
});

it('rejects a submission that mixes companies', function () {
    $a = submissionContext();
    $b = submissionContext();

    expect(fn () => app(P2pGoodReceiptSubmissionService::class)->start($a['user'], [$a['state']->id, $b['state']->id]))
        ->toThrow(ValidationException::class);
});

it('submits lines, bills the quantity and advances the GR and PO', function () {
    $c = submissionContext();
    $service = app(P2pGoodReceiptSubmissionService::class);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $invoice = $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 10],
    ], $c['user'], ['reference_number' => ['DR-1'], 'invoice_at' => now()]);

    expect($invoice->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($invoice->amount_submitted)->toBe('1000.00')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->is_fully_billed)->toBeTrue()
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($c['po']->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::FullySubmitted)
        ->and($invoice->histories)->toHaveCount(1);
});

it('supports a partial submission and keeps the rest billable', function () {
    $c = submissionContext(quantity: 10);
    $service = app(P2pGoodReceiptSubmissionService::class);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $invoice = $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 4],
    ], $c['user']);

    $line = $invoice->lines->first();

    expect($line->quantity_billed)->toBe(4)
        ->and($line->quantity_remaining)->toBe('6.00')
        ->and($line->is_fully_billed)->toBeFalse()
        ->and($service->remainingQuantityFor($c['item']->fresh()))->toBe(6);
});

it('rejects a quantity over the remaining amount', function () {
    $c = submissionContext(quantity: 5);
    $service = app(P2pGoodReceiptSubmissionService::class);

    $invoice = $service->start($c['user'], [$c['state']->id]);

    expect(fn () => $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 6],
    ], $c['user']))->toThrow(ValidationException::class);
});

it('rejects submitting an invoice that is not pending or returned', function () {
    $c = submissionContext();
    $service = app(P2pGoodReceiptSubmissionService::class);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $invoice->forceFill(['status' => P2pStatus::SubmittedToChecker])->save();

    expect(fn () => $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 1],
    ], $c['user']))->toThrow(ValidationException::class);
});

it('replaces lines when a returned invoice is resubmitted', function () {
    $c = submissionContext(quantity: 10);
    $service = app(P2pGoodReceiptSubmissionService::class);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $invoice = $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 4],
    ], $c['user']);

    $invoice->forceFill(['status' => P2pStatus::ReturnedToVendor])->save();
    $invoice = $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 10],
    ], $c['user']);

    expect($invoice->lines()->count())->toBe(1)
        ->and($invoice->lines()->first()->quantity_billed)->toBe(10);
});
