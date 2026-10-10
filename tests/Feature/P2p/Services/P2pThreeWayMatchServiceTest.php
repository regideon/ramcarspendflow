<?php

use App\Enums\P2pThreeWayMatchStatus;
use App\Models\P2p\InvoiceLine;
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
