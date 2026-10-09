<?php

namespace Database\Factories;

use App\Models\WithholdingTaxCode;
use App\Models\WithholdingTaxType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithholdingTaxCode>
 */
class WithholdingTaxCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'withholding_tax_type_id' => WithholdingTaxType::factory(),
            'code' => fake()->unique()->numerify('##'),
            'atc' => fake()->bothify('WC###'),
            'description' => fake()->words(3, true),
            'rate' => fake()->randomElement([1, 2, 5, 10, 15]),
            'is_active' => true,
        ];
    }
}
