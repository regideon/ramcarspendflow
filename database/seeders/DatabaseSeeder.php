<?php

namespace Database\Seeders;

use Database\Seeders\P2p\P2pDemoMasterDataSeeder;
use Database\Seeders\P2p\P2pDemoTransactionSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database for development/UAT. Master data seeders are safe
     * to re-run; DemoMasterDataSeeder adds generic (non-SAP) data and RoleAndUserSeeder
     * truncates and recreates users and roles. Neither runs in production.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            CurrencySeeder::class,
            AccountGroupSeeder::class,
            PaymentMethodSeeder::class,
            TaxCodeSeeder::class,
            WithholdingTaxSeeder::class,
            DemoMasterDataSeeder::class,
            RoleAndUserSeeder::class,
            P2pDemoMasterDataSeeder::class,
            P2pDemoTransactionSeeder::class,
            ApprovalMatrixSeeder::class,
            DueTypeSeeder::class,
        ]);
    }
}
