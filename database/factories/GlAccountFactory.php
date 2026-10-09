<?php

namespace Database\Factories;

use App\Models\GlAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlAccount>
 */
class GlAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('6######'),
            'description' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}
