<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['from_currency_id', 'to_currency_id', 'rate', 'valid_from'])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    /**
     * PHP value of 1 unit of the currency, using the latest rate valid on the date.
     * Returns null when no rate is on file.
     */
    public static function rateToPhp(Currency $currency, ?CarbonInterface $date = null): ?string
    {
        if ($currency->code === 'PHP') {
            return '1.000000';
        }

        return static::query()
            ->where('from_currency_id', $currency->id)
            ->whereHas('toCurrency', fn ($query) => $query->where('code', 'PHP'))
            ->whereDate('valid_from', '<=', ($date ?? now())->toDateString())
            ->latest('valid_from')
            ->value('rate');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'valid_from' => 'date',
        ];
    }
}
