<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use Illuminate\Database\Seeder;

class AccountGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountGroups = [
            '0001' => 'Vendor',
            '0007' => 'Plants',
            '0008' => 'Non-Trade Vendor',
            '0009' => 'Foreign-Trade Vendor',
            '0010' => 'Vendor (SCB)',
            '0013' => 'Employee Account',
        ];

        foreach ($accountGroups as $code => $description) {
            AccountGroup::updateOrCreate(['code' => $code], ['description' => $description]);
        }
    }
}
