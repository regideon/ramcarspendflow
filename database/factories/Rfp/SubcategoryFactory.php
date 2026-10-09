<?php

namespace Database\Factories\Rfp;

use App\Models\GlAccount;
use App\Models\Rfp\Category;
use App\Models\Rfp\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcategory>
 */
class SubcategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfp_category_id' => Category::factory(),
            'gl_account_id' => GlAccount::factory(),
            'name' => fake()->unique()->words(3, true),
            'document_type' => 'KR',
            'posting_key' => '40',
            'requires_cost_center' => true,
            'requires_po_number' => false,
            'is_active' => true,
        ];
    }
}
