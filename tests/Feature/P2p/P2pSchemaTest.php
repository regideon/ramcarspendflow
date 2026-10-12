<?php

use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

it('creates every p2p table', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'p2p_sap_purchase_orders',
    'p2p_sap_good_receipts',
    'p2p_sap_good_receipt_items',
    'p2p_invoices',
    'p2p_invoice_lines',
    'p2p_good_receipt_states',
    'p2p_approval_histories',
    'p2p_escalation_reasons',
    'p2p_escalation_reason_steps',
    'p2p_escalations',
    'p2p_items',
    'p2p_delivery_types',
    'p2p_material_classifications',
    'p2p_sloc_classifications',
    'p2p_material_classification_user',
    'p2p_email_notification_receivers',
    'p2p_sap_api_logs',
]);

it('generates an GRC number and a short code on create', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->grc_number)->toBe(sprintf('GRC-%08d', $invoice->id))
        ->and($invoice->code)->toHaveLength(8);
});

it('links an invoice to its vendor, company and submitter', function () {
    $vendor = Vendor::factory()->create();
    $company = Company::factory()->create();
    $user = User::factory()->create();

    $invoice = Invoice::factory()->create([
        'vendor_id' => $vendor->id,
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    expect($invoice->vendor->is($vendor))->toBeTrue()
        ->and($invoice->company->is($company))->toBeTrue()
        ->and($invoice->user->is($user))->toBeTrue();
});

it('bills invoice lines against SAP GR items', function () {
    $invoice = Invoice::factory()->create();
    $sapItem = SapGoodReceiptItem::factory()->create();

    $line = InvoiceLine::factory()->for($invoice, 'invoice')->create([
        'p2p_sap_good_receipt_item_id' => $sapItem->id,
    ]);

    expect($invoice->lines)->toHaveCount(1)
        ->and($line->sapItem->is($sapItem))->toBeTrue()
        ->and($sapItem->invoiceLines)->toHaveCount(1);
});

it('links a GR state to its SAP mirror receipt', function () {
    $receipt = SapGoodReceipt::factory()->create();
    $item = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create();

    expect($item->goodReceipt->is($receipt))->toBeTrue()
        ->and($receipt->items)->toHaveCount(1);
});
