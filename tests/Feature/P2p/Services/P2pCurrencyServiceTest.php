<?php

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\P2p\P2pCurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->php = Currency::factory()->create(['code' => 'PHP', 'name' => 'Philippine Peso']);
    $this->usd = Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);

    ExchangeRate::factory()->create([
        'from_currency_id' => $this->usd->id,
        'to_currency_id' => $this->php->id,
        'rate' => 58.25,
        'valid_from' => now()->subDay(),
    ]);
});

it('returns the latest rate to PHP', function () {
    expect(app(P2pCurrencyService::class)->rateToPhp('USD'))->toBe('58.250000');
});

it('treats PHP as the base currency', function () {
    expect(app(P2pCurrencyService::class)->rateToPhp('PHP'))->toBe('1.000000');
});

it('converts an amount to PHP', function () {
    expect(app(P2pCurrencyService::class)->toPhp('100', 'USD'))->toBe('5825.00');
});

it('returns null for an unknown currency', function () {
    expect(app(P2pCurrencyService::class)->rateToPhp('XXX'))->toBeNull()
        ->and(app(P2pCurrencyService::class)->toPhp('100', 'XXX'))->toBeNull();
});
