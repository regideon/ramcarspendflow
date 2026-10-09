<?php

namespace Database\Factories\Rfp;

use App\Models\Rfp\DueType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DueType>
 */
class DueTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('due-????'),
            'name' => fake()->word(),
            'description' => '5-day processing',
            'processing_days' => 5,
            'business_days_only' => true,
            'is_default' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
