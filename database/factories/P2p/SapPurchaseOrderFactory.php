<?php

namespace Database\Factories\P2p;

use App\Enums\P2pPurchaseOrderStatus;
use App\Models\P2p\SapPurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SapPurchaseOrder>
 */
class SapPurchaseOrderFactory extends Factory
{
    protected $model = SapPurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status_id' => P2pPurchaseOrderStatus::Pending,
            'company_code' => '1000',
            'purchase_order' => '45'.fake()->unique()->numerify('#########'),
            'vendor_code' => fake()->numerify('200###'),
            'purchase_order_date' => now()->subDays(14),
            'purchase_order_type' => 'NB',
            'changed_on' => now()->subDays(2),
            'created_by' => 'DEMO',
            'currency_key' => 'PHP',
            'total_amount' => fake()->randomFloat(2, 1000, 500000),
            'gr_numbers' => [],
            'has_down_payment' => false,
        ];
    }
}
