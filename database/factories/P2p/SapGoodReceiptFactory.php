<?php

namespace Database\Factories\P2p;

use App\Models\Company;
use App\Models\P2p\SapGoodReceipt;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SapGoodReceipt>
 */
class SapGoodReceiptFactory extends Factory
{
    protected $model = SapGoodReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order' => '45'.fake()->numerify('#########'),
            'company_id' => Company::factory(),
            'vendor_id' => Vendor::factory(),
            'good_receipt_number' => '50'.fake()->unique()->numerify('#########'),
            'reference_number' => fake()->bothify('DR-####'),
            'amount_total' => fake()->randomFloat(2, 500, 100000),
            'date_at' => now()->subDays(7),
            'upload_at' => now()->subDays(6),
        ];
    }
}
