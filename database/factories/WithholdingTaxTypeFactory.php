<?php

namespace Database\Factories;

use App\Models\WithholdingTaxType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithholdingTaxType>
 */
class WithholdingTaxTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??'),
            'name' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}
