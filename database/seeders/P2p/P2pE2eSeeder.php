<?php

namespace Database\Seeders\P2p;

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Item;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Deterministic P2P fixtures for the Playwright end-to-end suite
 * (`tests/e2e/p2p`). Creates one pending goods receipt per workflow step the
 * suite walks, then writes a manifest the specs read to know which PO/GR to
 * process.
 *
 * Only records under the E2E PO/GR prefixes are purged, so the rest of the
 * local data is left untouched, and the suite can run repeatedly.
 */
class P2pE2eSeeder extends Seeder
{
    /** PO numbers owned by the E2E suite (4599xxxxxx). */
    public const string PO_PREFIX = '4599';

    /** GR numbers owned by the E2E suite (5999xxxxxx). */
    public const string GR_PREFIX = '5999';

    public const int FIXTURE_COUNT = 12;

    /**
     * Which fixture each scenario uses. `B` and `C` get one fixture per
     * escalation reason so the specs stay independent of each other.
     */
    public const array FIXTURES = [
        'A' => 1,
        'B_qty' => 2,
        'B_incomplete' => 3,
        'B_item_desc' => 4,
        'B_uom' => 5,
        'B_other' => 6,
        'C_price' => 7,
        'C_other' => 8,
        'D' => 9,
        'spare_1' => 10,
        'spare_2' => 11,
        'spare_3' => 12,
    ];

    public const string MANIFEST_PATH = 'app/p2p-e2e-fixtures.json';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('P2pE2eSeeder creates test data and must not run in production.');
        }

        $vendor = Vendor::query()
            ->where('code', RoleAndUserSeeder::DEMO_VENDOR_CODE)
            ->first()
            ?? Vendor::query()->firstOrFail();

        $company = Company::query()->orderBy('code')->firstOrFail();

        $this->purge();

        $material = Item::query()->firstOrCreate(
            ['code' => 'RM-0001'],
            ['name' => 'Lead Ingot', 'uom' => 'KG', 'unit_cost' => 85.00, 'is_active' => true],
        );

        $fixtures = [];
        $runSuffix = now()->format('His');

        DB::transaction(function () use ($vendor, $company, $material, $runSuffix, &$fixtures): void {
            foreach (self::FIXTURES as $key => $sequence) {
                $poNumber = self::PO_PREFIX.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
                $grNumber = self::GR_PREFIX.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

                $receipt = SapGoodReceipt::create([
                    'purchase_order' => $poNumber,
                    'company_id' => $company->id,
                    'vendor_id' => $vendor->id,
                    'good_receipt_number' => $grNumber,
                    'reference_number' => 'DR-'.$grNumber,
                    'date_at' => now()->subDays(5)->toDateString(),
                    'upload_at' => now()->subDays(4)->toDateString(),
                    'amount_total' => 850.00,
                ]);

                SapGoodReceiptItem::create([
                    'p2p_sap_good_receipt_id' => $receipt->id,
                    'purchase_order' => $poNumber,
                    'purchase_order_item' => '00010',
                    'item_numbering' => 1,
                    'item_id' => $material->id,
                    'material_doc_year' => (int) now()->format('Y'),
                    'material_doc_item' => '0001',
                    'storage_location' => 'B001',
                    'item_name' => 'Lead Ingot',
                    'item_description' => 'Lead Ingot (E2E fixture)',
                    'quantity_po' => 10,
                    'quantity_served' => 10,
                    'uom' => 'KG',
                    'gr' => 10,
                    'unit_cost' => 85.00,
                    'net_price' => 85.00,
                    'price_unit' => 1,
                    'currency' => 'PHP',
                    'amount' => 850.00,
                    'quantity_po_balance' => 0,
                ]);

                $state = GoodReceiptState::create([
                    'p2p_sap_good_receipt_id' => $receipt->id,
                    'purchase_order' => $poNumber,
                    'good_receipt_number' => $grNumber,
                    'company_id' => $company->id,
                    'vendor_id' => $vendor->id,
                    'status' => P2pStatus::Pending,
                    'quantity_total' => 10,
                    'quantity_served' => 0,
                    'amount_total' => 850.00,
                    'is_fully_processed' => false,
                ]);

                SapPurchaseOrder::create([
                    'company_code' => $company->code,
                    'purchase_order' => $poNumber,
                    'vendor_code' => $vendor->code,
                    'purchase_order_date' => now()->subDays(30)->toDateString(),
                    'purchase_order_type' => 'NB',
                    'changed_on' => now()->subDays(3)->toDateString(),
                    'created_by' => 'E2E',
                    'currency_key' => 'PHP',
                    'gr_numbers' => [$grNumber],
                    'total_amount' => 850.00,
                    'quantity_po_balance' => 0,
                ]);

                $fixtures[$key] = [
                    'po' => $poNumber,
                    'gr' => $grNumber,
                    'state_id' => $state->id,
                    'reference' => 'E2E-'.$key.'-'.$runSuffix,
                ];
            }
        });

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'password' => RoleAndUserSeeder::PASSWORD,
            'users' => [
                'vendor' => 'vendor@ramcar.com',
                'processor' => 'ap.processor@ramcar.com',
                'supervisor' => 'ap.supervisor@ramcar.com',
                'procurement' => 'procurement@ramcar.com',
                'warehouse' => 'warehouse@ramcar.com',
            ],
            'tax_code' => 'I2',
            'fixtures' => $fixtures,
        ];

        $path = storage_path(self::MANIFEST_PATH);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->command?->info('Seeded '.count($fixtures).' P2P E2E fixtures: '.storage_path(self::MANIFEST_PATH));
    }

    /**
     * Remove only the records this seeder (and the specs) created.
     */
    protected function purge(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            $invoiceIds = DB::table('p2p_invoices')
                ->where('gr_numbers', 'like', '%'.self::GR_PREFIX.'%')
                ->pluck('id');

            if ($invoiceIds->isNotEmpty()) {
                DB::table('p2p_sap_api_logs')->whereIn('p2p_invoice_id', $invoiceIds)->delete();
                DB::table('p2p_approval_histories')->whereIn('p2p_invoice_id', $invoiceIds)->delete();
                DB::table('p2p_escalations')->whereIn('p2p_invoice_id', $invoiceIds)->delete();
                DB::table('p2p_invoice_lines')->whereIn('p2p_invoice_id', $invoiceIds)->delete();
                DB::table('p2p_invoices')->whereIn('id', $invoiceIds)->delete();
            }

            DB::table('p2p_good_receipt_states')
                ->where('good_receipt_number', 'like', self::GR_PREFIX.'%')
                ->delete();

            DB::table('p2p_sap_good_receipt_items')
                ->where('purchase_order', 'like', self::PO_PREFIX.'%')
                ->delete();

            DB::table('p2p_sap_good_receipts')
                ->where('purchase_order', 'like', self::PO_PREFIX.'%')
                ->delete();

            DB::table('p2p_sap_purchase_orders')
                ->where('purchase_order', 'like', self::PO_PREFIX.'%')
                ->delete();
        });
    }
}
