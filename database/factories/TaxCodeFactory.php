<?php

namespace Database\Factories;

use App\Enums\TaxCodeCategory;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCode>
 */
class TaxCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('?#'),
            'description' => fake()->words(3, true),
            'category' => TaxCodeCategory::Taxable,
            'rate' => 12,
            'tax_procedure' => 'TAXPH',
            'is_active' => true,
        ];
    }
}
