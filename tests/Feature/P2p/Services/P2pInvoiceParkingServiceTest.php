<?php

use App\Contracts\P2p\SapInvoiceParkingGateway;
use App\Enums\P2pStatus;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapApiLog;
use App\Models\User;
use App\Services\P2p\P2pInvoiceParkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

function parkingGateway(array $result): SapInvoiceParkingGateway
{
    return new class($result) implements SapInvoiceParkingGateway
    {
        public function __construct(private array $result) {}

        public function park(Invoice $invoice): array
        {
            return $this->result;
        }
    };
}

it('moves an approved invoice to Pending Post before calling SAP', function () {
    config(['p2p.sap.enabled' => true]);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToChecker]);
    $supervisor = User::factory()->create();

    app()->instance(SapInvoiceParkingGateway::class, parkingGateway([
        'success' => true,
        'invoice_number' => '5100000123',
        'fiscal_year' => '2026',
        'message' => 'Invoice parked',
    ]));

    $parked = app(P2pInvoiceParkingService::class)->park($invoice, $supervisor, 'Checked against the PO.');

    expect($parked->status)->toBe(P2pStatus::InvoiceParked)
        ->and($parked->invoice_number)->toBe('5100000123')
        ->and($parked->fiscal_year)->toBe(2026)
        ->and($parked->sap_parked_at)->not->toBeNull()
        ->and((int) $parked->sap_park_attempts)->toBe(1);

    $this->assertDatabaseHas('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::PendingPost->value,
        'actioned_by' => $supervisor->id,
    ]);

    $this->assertDatabaseHas('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::InvoiceParked->value,
        'actioned_by' => $supervisor->id,
    ]);
});

it('leaves the invoice at Pending Post and records the error when SAP fails', function () {
    config(['p2p.sap.enabled' => true]);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToChecker]);
    $supervisor = User::factory()->create();

    app()->instance(SapInvoiceParkingGateway::class, parkingGateway([
        'success' => false,
        'error' => 'Vendor not found in SAP',
    ]));

    expect(fn () => app(P2pInvoiceParkingService::class)->park($invoice, $supervisor, 'Checked.'))
        ->toThrow(RuntimeException::class, 'Vendor not found in SAP');

    $invoice->refresh();

    expect($invoice->status)->toBe(P2pStatus::PendingPost)
        ->and($invoice->sap_park_error)->toBe('Vendor not found in SAP')
        ->and((int) $invoice->sap_park_attempts)->toBe(1)
        ->and($invoice->invoice_number)->not->toBe('5100000123');

    $this->assertDatabaseMissing('p2p_approval_histories', [
        'p2p_invoice_id' => $invoice->id,
        'to_status' => P2pStatus::InvoiceParked->value,
    ]);
});

it('retries a failed park and completes it', function () {
    config(['p2p.sap.enabled' => true]);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToChecker]);
    $supervisor = User::factory()->create();

    app()->instance(SapInvoiceParkingGateway::class, parkingGateway([
        'success' => false,
        'error' => 'SAP timeout',
    ]));

    expect(fn () => app(P2pInvoiceParkingService::class)->park($invoice, $supervisor, 'Checked.'))
        ->toThrow(RuntimeException::class, 'SAP timeout');

    app()->instance(SapInvoiceParkingGateway::class, parkingGateway([
        'success' => true,
        'invoice_number' => '5100000124',
        'fiscal_year' => '2026',
        'message' => 'Invoice parked on retry',
    ]));

    $parked = app(P2pInvoiceParkingService::class)->retry($invoice->fresh(), $supervisor, 'Retry.');

    expect($parked->status)->toBe(P2pStatus::InvoiceParked)
        ->and($parked->invoice_number)->toBe('5100000124')
        ->and($parked->sap_park_error)->toBeNull()
        ->and((int) $parked->sap_park_attempts)->toBe(2);
});

it('does not call SAP when the integration is disabled', function () {
    config(['p2p.sap.enabled' => false]);

    $invoice = Invoice::factory()->create([
        'status' => P2pStatus::SubmittedToChecker,
        'invoice_number' => null,
    ]);
    $supervisor = User::factory()->create();

    app()->instance(SapInvoiceParkingGateway::class, new class implements SapInvoiceParkingGateway
    {
        public function park(Invoice $invoice): array
        {
            throw new RuntimeException('SAP should not be called while disabled.');
        }
    });

    $parked = app(P2pInvoiceParkingService::class)->park($invoice, $supervisor, 'Checked.');

    expect($parked->status)->toBe(P2pStatus::PendingPost)
        ->and($parked->invoice_number)->toBeNull();

    $this->assertDatabaseHas('p2p_sap_api_logs', [
        'p2p_invoice_id' => $invoice->id,
        'endpoint' => 'park_invoice',
        'type' => SapApiLog::TYPE_SOAP,
    ]);
});

it('rejects parking an invoice that is not awaiting the checker', function () {
    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToAp]);
    $supervisor = User::factory()->create();

    expect(fn () => app(P2pInvoiceParkingService::class)->park($invoice, $supervisor, 'Checked.'))
        ->toThrow(ValidationException::class);

    expect($invoice->fresh()->status)->toBe(P2pStatus::SubmittedToAp);
});
