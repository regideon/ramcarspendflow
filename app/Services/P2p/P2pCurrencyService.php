<?php

namespace App\Services\P2p;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;

/**
 * Currency lookups and conversion to PHP. Delegates the rate lookup to
 * {@see ExchangeRate::rateToPhp()} so P2P and RFP share one rule.
 */
class P2pCurrencyService
{
    public const string BASE = 'PHP';

    /**
     * Rate to PHP on a given day (latest valid rate), or null when unavailable.
     */
    public function rateToPhp(string $currencyCode, ?CarbonInterface $on = null): ?string
    {
        $currency = Currency::query()->where('code', $currencyCode)->first();

        return $currency === null ? null : ExchangeRate::rateToPhp($currency, $on);
    }

    /**
     * Convert an amount in the given currency to PHP, or null when no rate exists.
     */
    public function toPhp(string $amount, string $currencyCode, ?CarbonInterface $on = null): ?string
    {
        $rate = $this->rateToPhp($currencyCode, $on);

        return $rate === null ? null : bcmul($amount, $rate, 2);
    }
}
