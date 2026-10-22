<?php

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\User;
use App\Services\P2p\P2pInvoiceStatusPollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

pest()->use(RefreshDatabase::class);

function acdocaInvoice(P2pStatus $status): Invoice
{
    $company = Company::factory()->create(['code' => '1000']);

    return Invoice::factory()->create([
        'status' => $status,
        'company_id' => $company->id,
        'user_id' => User::factory(),
        'invoice_number' => '5100000123',
        'fiscal_year' => 2026,
        'po_numbers' => [],
    ]);
}

it('advances a parked invoice through parked, posted and completed one step per run', function () {
    config([
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.username' => 'odata-user',
        'p2p.sap.password' => 'odata-secret',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => false,
    ]);

    $invoice = acdocaInvoice(P2pStatus::PendingPost);

    $responses = [
        Http::response(['d' => ['results' => [['BELNR' => '1900000001', 'AUGBL' => '']]]]),
        Http::response(['d' => ['results' => [['BELNR' => '1900000001', 'AUGBL' => '']]]]),
        Http::response(['d' => ['results' => [['BELNR' => '1900000001', 'AUGBL' => '2000000001']]]]),
    ];

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$responses) {
        return array_shift($responses) ?? Http::response(['d' => ['results' => []]]);
    });

    $poll = app(P2pInvoiceStatusPollService::class);

    $poll->poll();
    expect($invoice->fresh()->status)->toBe(P2pStatus::InvoiceParked);

    $poll->poll();
    $posted = $invoice->fresh();
    expect($posted->status)->toBe(P2pStatus::InvoicePosted)
        ->and($posted->fi_doc_no)->toBe('1900000001');

    $poll->poll();
    $completed = $invoice->fresh();
    expect($completed->status)->toBe(P2pStatus::Completed)
        ->and($completed->clearing_doc_no)->toBe('2000000001')
        ->and($completed->completed_at)->not->toBeNull();

    $this->assertDatabaseHas('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::InvoiceParked->value,
    ]);
    $this->assertDatabaseHas('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::InvoicePosted->value,
    ]);
    $this->assertDatabaseHas('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::Completed->value,
    ]);
});

it('does not complete an invoice while the clearing document is blank', function () {
    config([
        'p2p.sap.base_url' => 'https://sap.test',
        'p2p.sap.client' => '200',
        'p2p.sap.verify_tls' => false,
        'p2p.sap.log_odata' => false,
    ]);

    $invoice = acdocaInvoice(P2pStatus::InvoicePosted);

    Http::preventStrayRequests();
    Http::fake([
        '*ZCDS_FI_ACDOCA*' => Http::response(['d' => ['results' => [['BELNR' => '1900000001', 'AUGBL' => '']]]]),
    ]);

    app(P2pInvoiceStatusPollService::class)->poll();

    expect($invoice->fresh()->status)->toBe(P2pStatus::InvoicePosted)
        ->and($invoice->fresh()->clearing_doc_no)->toBeNull()
        ->and($invoice->fresh()->completed_at)->toBeNull();
});
