<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
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

it('splits the GR so unselected items stay pending when only some lines are submitted', function () {
    $c = submissionContext(quantity: 10);
    $service = app(P2pGoodReceiptSubmissionService::class);

    $unselected = SapGoodReceiptItem::factory()->for($c['receipt'], 'goodReceipt')->create([
        'purchase_order' => $c['po']->purchase_order,
        'purchase_order_item' => '00020',
        'quantity_po' => 5,
        'quantity_served' => 5,
        'unit_cost' => 100,
    ]);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $invoice = $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 10],
    ], $c['user']);

    $partial = GoodReceiptState::query()
        ->where('good_receipt_number', $c['receipt']->good_receipt_number.'-P1')
        ->sole();

    expect($invoice->lines()->count())->toBe(1)
        ->and($invoice->gr_numbers)->toBe([$c['receipt']->good_receipt_number])
        ->and($c['item']->fresh()->p2p_sap_good_receipt_id)->toBe($c['receipt']->id)
        ->and($unselected->fresh()->p2p_sap_good_receipt_id)->toBe($partial->p2p_sap_good_receipt_id)
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($partial->status)->toBe(P2pStatus::Pending)
        ->and($partial->sapGoodReceipt->items->pluck('id')->all())->toBe([$unselected->id]);
});

it('does not split the GR when every line is submitted', function () {
    $c = submissionContext();
    $service = app(P2pGoodReceiptSubmissionService::class);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 10],
    ], $c['user']);

    expect(GoodReceiptState::query()->count())->toBe(1);
});

it('refuses to start a request for a non-pending goods receipt', function () {
    $c = submissionContext();
    $c['state']->forceFill(['status' => P2pStatus::SubmittedToAp])->save();

    expect(fn () => app(P2pGoodReceiptSubmissionService::class)->start($c['user'], [$c['state']->id]))
        ->toThrow(ValidationException::class);
});

it('computes remaining quantities for many items in one pass', function () {
    $c = submissionContext(quantity: 10);
    $service = app(P2pGoodReceiptSubmissionService::class);

    expect($service->remainingQuantitiesFor(collect()))->toBe([]);

    $invoice = $service->start($c['user'], [$c['state']->id]);
    $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 4],
    ], $c['user']);

    $items = SapGoodReceiptItem::query()->whereKey($c['item']->id)->get();

    expect($service->remainingQuantitiesFor($items))->toBe([$c['item']->id => 6])
        ->and($service->remainingQuantitiesFor($items, $invoice->fresh()))->toBe([$c['item']->id => 10]);
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

/**
 * A pending GR for the given PO/currency, sharing one company and vendor.
 */
function submissionForCurrency(string $poNumber, string $currency, Company $company, Vendor $vendor): GoodReceiptState
{
    SapPurchaseOrder::factory()->create([
        'purchase_order' => $poNumber,
        'currency_key' => $currency,
        'company_code' => $company->code,
        'vendor_code' => $vendor->code,
    ]);

    $receipt = SapGoodReceipt::factory()->create([
        'purchase_order' => $poNumber,
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
    ]);

    return GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id,
        'purchase_order' => $poNumber,
        'good_receipt_number' => $receipt->good_receipt_number,
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => P2pStatus::Pending,
    ]);
}

it('rejects a submission that mixes currencies', function () {
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create();

    $php = submissionForCurrency('4500000200', 'PHP', $company, $vendor);
    $usd = submissionForCurrency('4500000201', 'USD', $company, $vendor);

    expect(fn () => app(P2pGoodReceiptSubmissionService::class)->start(
        User::factory()->create(),
        [$php->id, $usd->id],
    ))->toThrow(ValidationException::class);
});

it('rejects lines from a purchase order outside the request', function () {
    $c = submissionContext();
    $service = app(P2pGoodReceiptSubmissionService::class);
    $invoice = $service->start($c['user'], [$c['state']->id]);

    $otherPo = SapPurchaseOrder::factory()->create([
        'purchase_order' => '4500000999',
        'company_code' => $c['po']->company_code,
        'vendor_code' => $c['po']->vendor_code,
    ]);

    $otherReceipt = SapGoodReceipt::factory()->create([
        'purchase_order' => $otherPo->purchase_order,
        'company_id' => $c['receipt']->company_id,
        'vendor_id' => $c['receipt']->vendor_id,
    ]);

    $otherItem = SapGoodReceiptItem::factory()->for($otherReceipt, 'goodReceipt')->create([
        'purchase_order' => $otherPo->purchase_order,
        'quantity_po' => 5,
        'quantity_served' => 5,
        'unit_cost' => 50,
    ]);

    expect(fn () => $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $otherItem->id, 'quantity_billed' => 1],
    ], $c['user']))->toThrow(ValidationException::class);
});

it('stores the SAP material number and short text on the invoice line', function () {
    $c = submissionContext();
    $c['item']->forceFill([
        'item_name' => 'OS01050',
        'item_description' => 'CLNMATL GLASS CLEANER, MR.MUSCLE 500ML*',
    ])->save();

    $service = app(P2pGoodReceiptSubmissionService::class);
    $invoice = $service->start($c['user'], [$c['state']->id]);
    $invoice = $service->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $c['item']->id, 'quantity_billed' => 1],
    ], $c['user']);

    $line = $invoice->lines()->first();

    expect($line->item_name)->toBe('OS01050')
        ->and($line->item_description)->toBe('CLNMATL GLASS CLEANER, MR.MUSCLE 500ML*');
});
