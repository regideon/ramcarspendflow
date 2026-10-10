<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\P2pGoodReceiptReturnService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

/**
 * A submitted invoice plus its GR/PO context.
 *
 * @return array{invoice: Invoice, state: GoodReceiptState, po: SapPurchaseOrder, user: User}
 */
function returnContext(): array
{
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000200']);
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

    return compact('invoice', 'state', 'po', 'user');
}

it('returns an invoice to the vendor and reopens the GR', function () {
    $c = returnContext();

    $invoice = app(P2pGoodReceiptReturnService::class)->returnToVendor($c['invoice'], $c['user'], 'Wrong UoM');

    expect($invoice->status)->toBe(P2pStatus::ReturnedToVendor)
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::ReturnedToVendor)
        ->and($c['po']->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::PartiallySubmitted)
        ->and($invoice->histories()->first()->is_visible_to_vendor)->toBeTrue();
});

it('requires remarks to return an invoice', function () {
    $c = returnContext();

    expect(fn () => app(P2pGoodReceiptReturnService::class)->returnToVendor($c['invoice'], $c['user'], '  '))
        ->toThrow(ValidationException::class);
});

it('cancels a returned request and makes the GR billable again', function () {
    $c = returnContext();
    $service = app(P2pGoodReceiptReturnService::class);

    $service->returnToVendor($c['invoice'], $c['user'], 'Please fix');
    $invoice = $service->cancel($c['invoice']->fresh(), $c['user'], 'No longer needed');

    expect($invoice->status)->toBe(P2pStatus::Cancelled)
        ->and($c['state']->fresh()->status)->toBe(P2pStatus::Pending)
        ->and($c['po']->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::Pending);
});

it('only cancels an invoice that is returned to the vendor', function () {
    $c = returnContext();

    expect(fn () => app(P2pGoodReceiptReturnService::class)->cancel($c['invoice'], $c['user'], 'Nope'))
        ->toThrow(ValidationException::class);
});
