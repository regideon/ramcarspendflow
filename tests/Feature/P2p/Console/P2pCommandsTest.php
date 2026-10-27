<?php

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Mail\P2p\ProcurementWarningMail;
use App\Models\Company;
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

it('dispatches one all-vendor daily sync job instead of one per vendor', function () {
    Queue::fake();
    Vendor::factory()->create(['code' => '200001', 'is_active' => true]);
    Vendor::factory()->create(['code' => '200002', 'is_active' => true]);

    $this->artisan('p2p:sync-daily-purchase-orders')->assertSuccessful();

    Queue::assertPushed(SyncP2pPurchaseOrders::class, 1);
    Queue::assertPushed(fn (SyncP2pPurchaseOrders $job): bool => ! isset($job->filters['lifnr']));
});

it('dispatches a targeted sync job per requested vendor', function () {
    Queue::fake();

    $this->artisan('p2p:sync-daily-purchase-orders --vendors=200001,200002')->assertSuccessful();

    Queue::assertPushed(SyncP2pPurchaseOrders::class, 2);
    Queue::assertPushed(fn (SyncP2pPurchaseOrders $job): bool => $job->filters['lifnr'] === '200001');
    Queue::assertPushed(fn (SyncP2pPurchaseOrders $job): bool => $job->filters['lifnr'] === '200002');
});

it('syncs one purchase order directly', function () {
    Company::factory()->create(['code' => '1000']);
    Vendor::factory()->create(['code' => '200000']);

    app()->instance(SapPurchaseOrderSource::class, new class implements SapPurchaseOrderSource
    {
        public function changedPurchaseOrders(array $filters = []): array
        {
            return [[
                'purchase_order' => '4500000500',
                'company_code' => '1000',
                'vendor_code' => '200000',
                'currency_key' => 'PHP',
                'total_amount' => '1000.00',
                'good_receipts' => [],
            ]];
        }
    });

    $this->artisan('p2p:sync-purchase-order 4500000500')->assertSuccessful();

    expect(SapPurchaseOrder::query()->where('purchase_order', '4500000500')->exists())->toBeTrue();
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
