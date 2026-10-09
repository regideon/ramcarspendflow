<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_currency_id' => Currency::factory(),
            'to_currency_id' => Currency::factory(),
            'rate' => fake()->randomFloat(6, 0.01, 100),
            'valid_from' => fake()->date(),
        ];
    }
}
