<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The four RFP companies. P2P's wider SAP company list (T001) is added by its sync.
     */
    public function run(): void
    {
        $companies = [
            ['code' => '1000', 'short_name' => 'PBI', 'name' => 'Philippine Batteries Inc.'],
            ['code' => '2000', 'short_name' => 'OMMC', 'name' => 'Oriental & Motolite Mktg'],
            ['code' => '4000', 'short_name' => 'RTI', 'name' => 'Ramcar Technology Inc.'],
            ['code' => '5000', 'short_name' => 'EERI', 'name' => 'Evergreen Envtl Resources'],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(['code' => $company['code']], $company);
        }
    }
}
