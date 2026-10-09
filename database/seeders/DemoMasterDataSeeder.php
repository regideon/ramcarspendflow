<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\GlAccount;
use App\Models\Rfp\Category;
use App\Models\Rfp\OneTimeVendor;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generic demo master data for development and UAT: vendors, G/L accounts, cost
 * centers, expense categories and exchange rates. There is no SAP integration yet,
 * so none of this is real SAP data. Safe to re-run (upserts by code/name).
 *
 * Requires CompanySeeder, CurrencySeeder and AccountGroupSeeder.
 */
class DemoMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoMasterDataSeeder creates generic demo data and must not run in production.');
        }

        DB::transaction(function (): void {
            $glAccounts = $this->seedGlAccounts();
            $this->seedCostCenters($glAccounts);
            $this->seedExpenseCategories($glAccounts);
            $this->seedVendors();
            $this->seedOneTimeVendors();
            $this->seedExchangeRates();
        });
    }

    /**
     * @return array<string, GlAccount> keyed by G/L code
     */
    protected function seedGlAccounts(): array
    {
        $glAccounts = [
            '6001020' => 'Transportation Allowance',
            '6001080' => 'Company Sponsored Activity',
            '6001130' => 'Employee Benefits - Others',
            '6011010' => 'Travel - Local',
            '6011020' => 'Travel - Foreign',
            '6021010' => 'Office Supplies',
            '6031010' => 'Repairs & Maintenance - Building',
            '6031020' => 'Repairs & Maintenance - Equipment',
            '6041010' => 'Communication - Telephone & Internet',
            '6051010' => 'Professional Fees',
            '6061010' => 'Rental - Office',
            '6071010' => 'Utilities - Electricity',
            '6071020' => 'Utilities - Water',
            '6101130' => 'A&P Digital',
            '6111010' => 'Representation',
            '5021010' => 'Factory Supplies',
            '5031010' => 'Repairs & Maintenance - Plant Building',
            '5031020' => 'Repairs & Maintenance - Plant Equipment',
            '5071010' => 'Utilities - Plant Electricity',
            '1151010' => 'Advances to Suppliers',
        ];

        $models = [];

        foreach ($glAccounts as $code => $description) {
            $models[$code] = GlAccount::updateOrCreate(['code' => $code], ['description' => $description]);
        }

        return $models;
    }

    /**
     * Four cost centers per company, each allowed a set of G/L accounts. The 3rd digit
     * of the code is the expense type: 1–2 = OPEX (G/L 6…), 3 and above = MFG (G/L 5…).
     *
     * @param  array<string, GlAccount>  $glAccounts
     */
    protected function seedCostCenters(array $glAccounts): void
    {
        $costCenters = [
            // suffix => [description, expense group, allowed G/L codes]
            '1100' => ['Office of the President', 'Operating Expenses', ['6001080', '6011010', '6011020', '6111010', '6051010', '6031010', '6031020']],
            '1200' => ['Finance & Accounting', 'Operating Expenses', ['6001020', '6021010', '6041010', '6051010', '6011010', '6061010', '6071010', '6071020']],
            '2100' => ['Sales & Marketing', 'Operating Expenses', ['6101130', '6011010', '6111010', '6041010', '6001130']],
            '3100' => ['Plant Operations', 'Manufacturing Expenses', ['5021010', '5031010', '5031020', '5071010']],
        ];

        foreach (Company::query()->get() as $company) {
            $codes = collect(array_keys($costCenters))->map(fn (string $suffix): string => substr($company->code, 0, 1).'0'.$suffix);

            // Earlier demo codes did not follow the OPEX/MFG digit; master data is deactivated, never deleted
            CostCenter::query()->where('company_id', $company->id)->whereNotIn('code', $codes)->update(['is_active' => false]);

            foreach ($costCenters as $suffix => [$description, $expenseGroup, $glCodes]) {
                $costCenter = CostCenter::updateOrCreate(
                    ['company_id' => $company->id, 'code' => substr($company->code, 0, 1).'0'.$suffix],
                    ['description' => $description, 'is_active' => true],
                );

                $costCenter->glAccounts()->sync(
                    collect($glCodes)->mapWithKeys(fn (string $code): array => [
                        $glAccounts[$code]->id => ['expense_group' => $expenseGroup, 'account_type' => 'Accounts Payable'],
                    ])->all(),
                );
            }
        }
    }

    /**
     * @param  array<string, GlAccount>  $glAccounts
     */
    protected function seedExpenseCategories(array $glAccounts): void
    {
        $categories = [
            // category => [subcategory => [G/L code, requires cost center, requires PO no.]]
            'Employee Benefits' => [
                'Transportation Allowance' => ['6001020', true, false],
                'Company Sponsored Activity' => ['6001080', true, false],
                'Benefits - Others' => ['6001130', true, false],
            ],
            'Travel Expense' => [
                'Local Travel' => ['6011010', true, false],
                'Foreign Travel' => ['6011020', true, false],
            ],
            'Supplies' => [
                'Office Supplies' => ['6021010', true, false],
                'Factory Supplies' => ['5021010', true, false],
            ],
            'Repair & Maintenance' => [
                'Building Repairs' => ['6031010', true, false],
                'Equipment Repairs' => ['6031020', true, false],
                'Plant Building Repairs' => ['5031010', true, false],
                'Plant Equipment Repairs' => ['5031020', true, false],
            ],
            'Communication' => [
                'Telephone & Internet' => ['6041010', true, false],
            ],
            'Professional Fees' => [
                'Legal & Consulting' => ['6051010', true, false],
            ],
            'Rental' => [
                'Office Rental' => ['6061010', true, false],
            ],
            'Utilities' => [
                'Electricity' => ['6071010', true, false],
                'Water' => ['6071020', true, false],
                'Plant Electricity' => ['5071010', true, false],
            ],
            'Ads & Promo' => [
                'Digital Advertising' => ['6101130', true, false],
            ],
            'Representation' => [
                'Client Meals & Gifts' => ['6111010', true, false],
            ],
            'Advances to Supplier' => [
                'Supplier Down Payment' => ['1151010', false, true],
            ],
        ];

        foreach ($categories as $categoryName => $subcategories) {
            $category = Category::updateOrCreate(['name' => $categoryName]);

            foreach ($subcategories as $name => [$glCode, $requiresCostCenter, $requiresPoNumber]) {
                $category->subcategories()->updateOrCreate(['name' => $name], [
                    'gl_account_id' => $glAccounts[$glCode]->id,
                    'document_type' => 'KR',
                    'posting_key' => '40',
                    'requires_cost_center' => $requiresCostCenter,
                    'requires_po_number' => $requiresPoNumber,
                ]);
            }
        }
    }

    /**
     * Generic vendors, each extended to every company.
     */
    protected function seedVendors(): void
    {
        $tradeGroup = AccountGroup::query()->where('code', '0001')->first();
        $nonTradeGroup = AccountGroup::query()->where('code', '0008')->first();
        $foreignGroup = AccountGroup::query()->where('code', '0009')->first();
        $employeeGroup = AccountGroup::query()->where('code', '0013')->first();

        $vendors = [
            // code => [name, email, country, currency, account group, payment terms]
            '200001' => ['Alpha Office Supplies Inc.', 'billing@alpha-supplies.example', 'PH', 'PHP', $tradeGroup, 'V30N'],
            '200002' => ['Bravo Industrial Parts Corp.', 'ar@bravo-parts.example', 'PH', 'PHP', $tradeGroup, 'V30N'],
            '200003' => ['Charlie Logistics Services', 'invoices@charlie-logistics.example', 'PH', 'PHP', $tradeGroup, 'V15N'],
            '200004' => ['Delta Facilities Maintenance', 'accounts@delta-fm.example', 'PH', 'PHP', $nonTradeGroup, 'V15N'],
            '200005' => ['Echo Telecom Philippines', 'billing@echo-telecom.example', 'PH', 'PHP', $nonTradeGroup, 'VCOD'],
            '200006' => ['Foxtrot Legal & Advisory', 'finance@foxtrot-legal.example', 'PH', 'PHP', $nonTradeGroup, 'V30N'],
            '200007' => ['Golf Property Leasing', 'leasing@golf-property.example', 'PH', 'PHP', $nonTradeGroup, 'V30N'],
            '200008' => ['Hotel Power & Utilities', 'collections@hotel-power.example', 'PH', 'PHP', $nonTradeGroup, 'VCOD'],
            '300001' => ['India Raw Materials Ltd.', 'ar@india-rawmat.example', 'SG', 'USD', $foreignGroup, 'V60N'],
            '300002' => ['Juliet Machinery GmbH', 'billing@juliet-machinery.example', 'DE', 'EUR', $foreignGroup, 'V60N'],
            // Employee accounts: the "Final payee" of a Reimbursement
            '900001' => ['Requestor (demo employee)', 'requestor@ramcar.com', 'PH', 'PHP', $employeeGroup, 'VCOD'],
            '900002' => ['Line Manager (demo employee)', 'line.manager@ramcar.com', 'PH', 'PHP', $employeeGroup, 'VCOD'],
        ];

        $companyIds = Company::query()->pluck('id');

        foreach ($vendors as $code => [$name, $email, $country, $currency, $accountGroup, $paymentTerms]) {
            $vendor = Vendor::updateOrCreate(['code' => $code], [
                'name' => $name,
                'email' => $email,
                'country' => $country,
                'currency' => $currency,
                'tax_number' => $country === 'PH' ? sprintf('000-%03d-%03d-000', (int) substr($code, 0, 3), (int) substr($code, -3)) : null,
                'account_group_id' => $accountGroup?->id,
            ]);

            $vendor->companies()->sync($companyIds->mapWithKeys(fn (int $companyId): array => [
                $companyId => [
                    'reconciliation_account' => $country === 'PH' ? '2000000' : '2000100',
                    'payment_terms' => $paymentTerms,
                    'payment_methods' => $country === 'PH' ? 'CT' : 'X',
                ],
            ])->all());
        }
    }

    protected function seedOneTimeVendors(): void
    {
        $oneTimeVendors = [
            ['name' => 'Juan Dela Cruz', 'street' => '123 Rizal St.', 'city' => 'Makati City', 'tin' => '123-456-789-000'],
            ['name' => 'Maria Santos', 'street' => '45 Mabini Ave.', 'city' => 'Quezon City', 'tin' => '987-654-321-000'],
        ];

        foreach ($oneTimeVendors as $oneTimeVendor) {
            OneTimeVendor::updateOrCreate(['tin' => $oneTimeVendor['tin']], $oneTimeVendor);
        }
    }

    /**
     * Sample rates to PHP, valid from the first day of the current month.
     */
    protected function seedExchangeRates(): void
    {
        $php = Currency::query()->where('code', 'PHP')->first();

        if ($php === null) {
            return;
        }

        $rates = ['USD' => 58.250000, 'EUR' => 63.100000, 'JPY' => 0.390000, 'SGD' => 43.500000];

        foreach ($rates as $code => $rate) {
            $currency = Currency::query()->where('code', $code)->first();

            if ($currency === null) {
                continue;
            }

            ExchangeRate::updateOrCreate(
                ['from_currency_id' => $currency->id, 'to_currency_id' => $php->id, 'valid_from' => now()->startOfMonth()],
                ['rate' => $rate],
            );
        }
    }
}
