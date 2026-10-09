<?php

namespace Database\Factories\Rfp;

use App\Models\Rfp\OneTimeVendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OneTimeVendor>
 */
class OneTimeVendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'tin' => fake()->numerify('###-###-###-000'),
        ];
    }
}
