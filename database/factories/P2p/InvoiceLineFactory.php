<?php

namespace Database\Factories\P2p;

use App\Enums\P2pMaterialType;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 50);
        $unitCost = fake()->randomFloat(2, 10, 5000);

        return [
            'p2p_invoice_id' => Invoice::factory(),
            'purchase_order' => '45'.fake()->numerify('#########'),
            'purchase_order_item' => '00010',
            'storage_location' => 'B001',
            'material_type' => P2pMaterialType::RawMaterial,
            'item_name' => fake()->words(2, true),
            'quantity_po' => $quantity,
            'quantity_billed' => $quantity,
            'quantity_remaining' => 0,
            'is_fully_billed' => true,
            'uom' => 'PC',
            'unit_cost' => $unitCost,
            'amount' => round($quantity * $unitCost, 2),
        ];
    }
}
