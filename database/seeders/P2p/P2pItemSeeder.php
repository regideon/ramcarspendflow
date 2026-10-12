<?php

namespace Database\Seeders\P2p;

use App\Models\P2p\Item;
use Illuminate\Database\Seeder;

/**
 * The SAP item master used by P2P, taken from `p2p_prod_clone.items`. The legacy
 * table stores the SAP material number in `name` and the description separately;
 * here `code` is the material number and `name` the description. Re-runnable.
 */
class P2pItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'E3832028' => ['OUTLET, EXTENSION CORD', 700.0000],
            'E3832029' => ['OUTLET, EXTENSION CORD 2', 700.0000],
        ];

        foreach ($items as $code => [$name, $unitCost]) {
            Item::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'unit_cost' => $unitCost,
                'is_active' => true,
            ]);
        }

        Item::query()->whereNotIn('code', array_keys($items))->update(['is_active' => false]);
    }
}
