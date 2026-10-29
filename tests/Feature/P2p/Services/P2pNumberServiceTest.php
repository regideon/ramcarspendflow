<?php

use App\Models\P2p\Invoice;
use App\Services\P2p\P2pNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

it('numbers a request with its creation year and GRC sequence', function () {
    $this->travelTo('2026-03-04 09:00:00');

    $invoice = Invoice::factory()->create();

    expect($invoice->p2p_number)->toBe(sprintf('P2P-2026-%08d', $invoice->id));
});

it('keeps growing past eight digits instead of wrapping', function () {
    $this->travelTo('2026-03-04 09:00:00');

    $number = app(P2pNumberService::class)->generate(100_000_000, now());

    expect($number)->toBe('P2P-2026-100000000');
});
