<?php

namespace Database\Seeders;

use App\Enums\TaxCodeCategory;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class TaxCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Input VAT codes (procedure TAXPH), merged from the RFP portal and P2P lists.
     */
    public function run(): void
    {
        $taxCodes = [
            ['I0', 'Zero Rated', TaxCodeCategory::ZeroRated, 0],
            ['I2', 'Input Tax 12% - Goods', TaxCodeCategory::Taxable, 12],
            ['I3', 'Input Tax 12% - Services', TaxCodeCategory::Taxable, 12],
            ['I4', 'Input Tax 12% - Imported', TaxCodeCategory::Taxable, 12],
            ['I5', 'Input Tax 12% - Capital Goods', TaxCodeCategory::Taxable, 12],
            ['I6', 'Deferred Input Tax 12% - Services', TaxCodeCategory::Taxable, 12],
            ['I7', 'Input VAT - Non-Resident Digital Service Providers', TaxCodeCategory::Taxable, 12],
            ['I8', 'Input VAT - Purchase of Goods from RBEs', TaxCodeCategory::Taxable, 12],
            ['I9', 'Input VAT - Purchase of Services from RBEs', TaxCodeCategory::Taxable, 12],
            ['IB', 'Importation of Goods - Pass thru BOC', TaxCodeCategory::Taxable, 0],
            ['IE', 'Exempt Entities', TaxCodeCategory::VatExempt, 0],
            ['IF', 'Foreign Transactions', TaxCodeCategory::VatExempt, 0],
            ['IN', 'Non-VAT', TaxCodeCategory::VatExempt, 0],
            ['IW', 'Without Proper Support Doc', TaxCodeCategory::VatExempt, 0],
            ['IX', 'VAT Exempt', TaxCodeCategory::VatExempt, 0],
        ];

        foreach ($taxCodes as [$code, $description, $category, $rate]) {
            TaxCode::updateOrCreate(['code' => $code], [
                'description' => $description,
                'category' => $category,
                'rate' => $rate,
            ]);
        }
    }
}
