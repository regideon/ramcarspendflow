<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Mail\P2p\EscalatedInvoiceNoticeMail;
use App\Mail\P2p\EscalateToProcurementMail;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

/**
 * @return array{invoice: Invoice, state: GoodReceiptState, user: User}
 */
function mailEscalationContext(): array
{
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000600']);
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
    $submission->submit($invoice, [
        ['p2p_sap_good_receipt_item_id' => $item->id, 'quantity_billed' => 10],
    ], $user);

    return compact('invoice', 'state', 'user');
}

it('queues an action-required mail to the current hop handlers', function () {
    Mail::fake();
    $c = mailEscalationContext();

    User::factory()->create(['email' => 'warehouse@ramcar.com'])->assignRole(Role::findOrCreate(RoleName::Warehouse->value, 'web'));

    $reason = EscalationReason::create(['code' => 'quantity_difference', 'name' => 'Quantity Difference', 'origin_role' => 'vendor', 'is_active' => true]);
    $reason->steps()->create(['step_order' => 1, 'role' => 'warehouse', 'status_id' => P2pStatus::EscalatedToWarehouse->value]);

    app(P2pEscalationRoutingService::class)->start($c['invoice'], 'quantity_difference', [$c['state']->id], $c['user'], 'Short');

    Mail::assertQueued(EscalateToProcurementMail::class);
});

it('queues an origin notice when the vendor has an email', function () {
    Mail::fake();
    $c = mailEscalationContext();
    $c['invoice']->vendor?->forceFill(['email' => 'vendor@example.com'])->save();

    $reason = EscalationReason::create(['code' => 'quantity_difference', 'name' => 'Quantity Difference', 'origin_role' => 'vendor', 'is_active' => true]);
    $reason->steps()->create(['step_order' => 1, 'role' => 'vendor', 'status_id' => P2pStatus::ReturnedToVendor->value]);

    app(P2pEscalationRoutingService::class)->start($c['invoice']->fresh(), 'quantity_difference', [$c['state']->id], $c['user'], 'Short');

    Mail::assertQueued(EscalatedInvoiceNoticeMail::class);
});
