<?php

use App\Enums\P2pThreeWayMatchStatus;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Services\P2p\P2pThreeWayMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

it('matches when the invoice equals the receipt and the PO is fully received', function () {
    $item = SapGoodReceiptItem::factory()->create(['quantity_po' => 10, 'quantity_served' => 10]);
    $line = InvoiceLine::factory()->create(['quantity_billed' => 10]);

    expect(app(P2pThreeWayMatchService::class)->match($item, $line))->toBe(P2pThreeWayMatchStatus::Matched);
});

it('flags an open PO when the PO still has quantity outstanding', function () {
    $item = SapGoodReceiptItem::factory()->create(['quantity_po' => 20, 'quantity_served' => 10]);
    $line = InvoiceLine::factory()->create(['quantity_billed' => 10]);

    expect(app(P2pThreeWayMatchService::class)->match($item, $line))->toBe(P2pThreeWayMatchStatus::OpenPo);
});

it('flags no match when invoiced more than received', function () {
    $item = SapGoodReceiptItem::factory()->create(['quantity_po' => 10, 'quantity_served' => 10]);
    $line = InvoiceLine::factory()->create(['quantity_billed' => 12]);

    expect(app(P2pThreeWayMatchService::class)->match($item, $line))->toBe(P2pThreeWayMatchStatus::NoMatch);
});

it('returns null when the line has no mirror item', function () {
    $line = InvoiceLine::factory()->create();

    expect(app(P2pThreeWayMatchService::class)->matchLine($line))->toBeNull();
});

it('aggregates a PO line across several goods receipts', function () {
    $receiptA = SapGoodReceipt::factory()->create(['purchase_order' => '4500001000', 'good_receipt_number' => '5000001000']);
    $receiptB = SapGoodReceipt::factory()->create(['purchase_order' => '4500001000', 'good_receipt_number' => '5000001001']);

    $itemA = SapGoodReceiptItem::factory()->for($receiptA, 'goodReceipt')->create([
        'purchase_order' => '4500001000',
        'purchase_order_item' => '00010',
        'quantity_po' => 10,
        'quantity_served' => 6,
    ]);
    SapGoodReceiptItem::factory()->for($receiptB, 'goodReceipt')->create([
        'purchase_order' => '4500001000',
        'purchase_order_item' => '00010',
        'quantity_po' => 10,
        'quantity_served' => 4,
    ]);

    InvoiceLine::factory()->create(['p2p_sap_good_receipt_item_id' => $itemA->id, 'quantity_billed' => 4]);

    $breakdown = app(P2pThreeWayMatchService::class)->breakdown('4500001000', '00010');

    expect($breakdown['ordered'])->toBe(10)
        ->and($breakdown['received'])->toBe(10)
        ->and($breakdown['invoiced'])->toBe(4)
        ->and($breakdown['rows'])->toHaveCount(2);

    $withPending = app(P2pThreeWayMatchService::class)->breakdown('4500001000', '00010', 6);

    expect($withPending['invoiced'])->toBe(10)
        ->and($withPending['adjustment'])->toBe(6)
        ->and($withPending['status'])->toBe(P2pThreeWayMatchStatus::Matched->value);
});
