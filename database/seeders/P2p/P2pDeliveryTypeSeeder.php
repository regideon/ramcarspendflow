<?php

namespace Database\Seeders\P2p;

use App\Models\P2p\DeliveryType;
use Illuminate\Database\Seeder;

/**
 * The SAP delivery types used by P2P, taken from `p2p_prod_clone.delivery_types`.
 * Re-runnable; any other delivery type is deactivated (never deleted).
 */
class P2pDeliveryTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'full' => ['Full Delivery', 1],
            'partial' => ['Partial Delivery', 2],
        ];

        foreach ($types as $code => [$name, $sort]) {
            DeliveryType::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'sort' => $sort,
                'is_active' => true,
            ]);
        }

        DeliveryType::query()->whereNotIn('code', array_keys($types))->update(['is_active' => false]);
    }
}
