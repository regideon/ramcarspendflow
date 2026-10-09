<?php

namespace Database\Factories\Rfp;

use App\Models\Currency;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\Rfp\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfp_id' => Rfp::factory(),
            'sort' => 1,
            'rfp_subcategory_id' => Subcategory::factory(),
            'invoice_number' => fake()->bothify('INV-#####'),
            'invoice_date' => now()->subDays(3),
            'description' => fake()->sentence(3),
            'currency_id' => fn () => Currency::query()->where('code', 'PHP')->value('id') ?? Currency::factory()->create(['code' => 'PHP'])->id,
            'amount' => fake()->randomFloat(2, 100, 50_000),
            'attachments' => ['rfp-attachments/sample.pdf'],
        ];
    }
}
