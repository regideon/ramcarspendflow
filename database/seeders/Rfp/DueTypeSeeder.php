<?php

namespace Database\Seeders\Rfp;

use App\Models\Rfp\DueType;
use Illuminate\Database\Seeder;

/**
 * Due types of the legacy RFP form. Safe to re-run (upserts by code); admins
 * may change the days afterwards, so existing rows keep their settings.
 */
class DueTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dueTypes = [
            ['code' => 'urgent', 'name' => 'Urgent', 'description' => '1-day processing', 'processing_days' => 1, 'is_default' => false, 'sort_order' => 1],
            ['code' => 'standard', 'name' => 'Standard', 'description' => '5-day processing', 'processing_days' => 5, 'is_default' => true, 'sort_order' => 2],
        ];

        foreach ($dueTypes as $dueType) {
            DueType::firstOrCreate(['code' => $dueType['code']], $dueType + ['business_days_only' => true]);
        }
    }
}
