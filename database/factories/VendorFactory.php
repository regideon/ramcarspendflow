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
            'code' => fake()->unique()->numerify('2#####'),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'country' => 'PH',
            'tax_number' => fake()->numerify('###-###-###-000'),
            'currency' => 'PHP',
            'is_active' => true,
        ];
    }
}
