<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => fake()->unique()->numerify('1#####'),
            'description' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}
