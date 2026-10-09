<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            'B' => 'BDO Withdrawal Slip',
            'C' => 'Check',
            'D' => 'Demand Draft',
            'L' => 'BDO Telex-Debit Letter',
            'M' => "Manager's Check",
            'R' => 'SBC - Telegraphic Transfer',
            'S' => 'SBC Withdrawal Slip',
            'T' => 'Bank Transfer',
            'W' => 'Post-dated Check',
            'X' => 'Telegraphic Transfer',
        ];

        foreach ($paymentMethods as $code => $description) {
            PaymentMethod::updateOrCreate(['country' => 'PH', 'code' => $code], ['description' => $description]);
        }
    }
}
