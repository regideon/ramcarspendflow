<?php

namespace Database\Factories\P2p;

use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SapGoodReceiptItem>
 */
class SapGoodReceiptItemFactory extends Factory
{
    protected $model = SapGoodReceiptItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitCost = fake()->randomFloat(2, 10, 5000);

        return [
            'p2p_sap_good_receipt_id' => SapGoodReceipt::factory(),
            'purchase_order' => '45'.fake()->numerify('#########'),
            'purchase_order_item' => '00010',
            'item_numbering' => 1,
            'material_doc_year' => (int) now()->format('Y'),
            'material_doc_item' => '0001',
            'storage_location' => 'B001',
            'item_name' => fake()->words(2, true),
            'quantity_po' => $quantity,
            'quantity_served' => $quantity,
            'uom' => 'PC',
            'unit_cost' => $unitCost,
            'amount' => round($quantity * $unitCost, 2),
        ];
    }
}
