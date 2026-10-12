<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Prefixed so it never collides with a SAP/seeded vendor code (all numeric).
            'code' => 'DEMO'.fake()->unique()->numberBetween(100000, 999999),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'country' => 'PH',
            'tax_number' => fake()->numerify('###-###-###-000'),
            'currency' => 'PHP',
            'is_active' => true,
        ];
    }
}
