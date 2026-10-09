<?php

namespace Database\Factories;

use App\Models\AccountGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountGroup>
 */
class AccountGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('00##'),
            'description' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
