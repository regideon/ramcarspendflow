<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Mail\P2p\ProcurementWarningMail;
use App\Models\P2p\EmailNotificationReceiver;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

pest()->use(RefreshDatabase::class);

it('dispatches a daily sync job per enabled vendor', function () {
    Queue::fake();
    Vendor::factory()->create(['code' => '200001', 'is_active' => true]);

    $this->artisan('p2p:sync-daily-purchase-orders')->assertSuccessful();

    Queue::assertPushed(SyncP2pPurchaseOrders::class);
});

it('backfills PO statuses only when --write is passed', function () {
    $po = SapPurchaseOrder::factory()->create(['purchase_order' => '4500000700', 'status_id' => P2pPurchaseOrderStatus::Pending]);
    GoodReceiptState::create(['purchase_order' => '4500000700', 'good_receipt_number' => '5000000700', 'status' => P2pStatus::SubmittedToAp]);

    $this->artisan('p2p:backfill-statuses')->assertSuccessful();
    expect($po->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::Pending);

    $this->artisan('p2p:backfill-statuses --write')->assertSuccessful();
    expect($po->fresh()->status_id)->toBe(P2pPurchaseOrderStatus::FullySubmitted);
});

it('sends overdue warnings once and stamps the invoice', function () {
    Mail::fake();
    EmailNotificationReceiver::create(['email' => 'poc@ramcar.com', 'name' => 'POC', 'is_active' => true]);

    $invoice = Invoice::factory()->create(['warning_sent_at' => null]);
    $reason = EscalationReason::create(['code' => 'other', 'name' => 'Other', 'origin_role' => 'any', 'is_active' => true]);

    Escalation::create([
        'escalation_number' => 'ESC-20261010-000001',
        'p2p_invoice_id' => $invoice->id,
        'p2p_escalation_reason_id' => $reason->id,
        'status' => 'open',
        'current_role' => 'procurement',
        'escalated_at' => now()->subDays(4),
    ]);

    $this->artisan('p2p:send-overdue-warnings')->assertSuccessful();

    Mail::assertQueued(ProcurementWarningMail::class);
    expect($invoice->fresh()->warning_sent_at)->not->toBeNull();

    $this->artisan('p2p:send-overdue-warnings')->assertSuccessful();
    Mail::assertQueued(ProcurementWarningMail::class, 1);
});
