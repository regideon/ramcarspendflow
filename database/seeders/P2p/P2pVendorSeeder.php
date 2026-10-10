<?php

namespace Database\Seeders\P2p;

use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports the SAP vendor master from `database/data/LocalVendor.csv` (columns:
 * CoCd, Company, Supplier, Name, Group Description). One row per vendor/company
 * extension, so the same vendor may appear once per company it is extended to.
 *
 * Vendors are upserted by SAP code (LIFNR) and linked to the matching company via
 * `company_vendor`. Only `code`/`name` are written; richer fields (email, currency,
 * account group) are left for SAP sync or manual maintenance. Re-runnable, and
 * streamed in chunks so the ~9.8k-row file stays memory-light.
 */
class P2pVendorSeeder extends Seeder
{
    /** Reconciliation account (AKONT) placeholder for imported vendors. */
    private const string RECONCILIATION_ACCOUNT = '2000000';

    private const int CHUNK = 500;

    public function run(): void
    {
        $handle = fopen(database_path('data/LocalVendor.csv'), 'r');

        if ($handle === false) {
            return;
        }

        $companyIdsByCode = Company::query()->pluck('id', 'code');

        /** @var array<string, array<string, mixed>> $vendors keyed by SAP code */
        $vendors = [];
        /** @var array<string, array{company: string, vendor: string}> $links */
        $links = [];
        $heading = true;

        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if ($heading) {
                $heading = false;

                continue;
            }

            $companyCode = trim((string) ($row[0] ?? ''));
            $code = trim((string) ($row[2] ?? ''));

            if ($code === '') {
                continue;
            }

            $name = trim((string) ($row[3] ?? ''));
            $now = now();

            $vendors[$code] = [
                'code' => $code,
                'name' => $name !== '' ? mb_substr($name, 0, 150) : $code,
                'country' => 'PH',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($companyCode !== '' && $companyIdsByCode->has($companyCode)) {
                $links[$companyCode.'|'.$code] = ['company' => $companyCode, 'vendor' => $code];
            }

            if (count($vendors) >= self::CHUNK) {
                $this->flush($vendors, $links, $companyIdsByCode);
                $vendors = [];
                $links = [];
            }
        }

        fclose($handle);

        $this->flush($vendors, $links, $companyIdsByCode);
    }

    /**
     * Upsert a chunk of vendors and link them to their companies.
     *
     * @param  array<string, array<string, mixed>>  $vendors
     * @param  array<string, array{company: string, vendor: string}>  $links
     * @param  Collection<string, int>  $companyIdsByCode
     */
    private function flush(array $vendors, array $links, Collection $companyIdsByCode): void
    {
        if ($vendors === [] && $links === []) {
            return;
        }

        DB::transaction(function () use ($vendors, $links, $companyIdsByCode): void {
            Vendor::query()->upsert(array_values($vendors), ['code'], ['name', 'is_active', 'updated_at']);

            if ($links === []) {
                return;
            }

            $vendorIdsByCode = Vendor::query()
                ->whereIn('code', array_keys($vendors))
                ->pluck('id', 'code');

            $now = now();
            $pivot = [];

            foreach ($links as $link) {
                $companyId = $companyIdsByCode->get($link['company']);
                $vendorId = $vendorIdsByCode->get($link['vendor']);

                if ($companyId === null || $vendorId === null) {
                    continue;
                }

                $pivot[] = [
                    'company_id' => $companyId,
                    'vendor_id' => $vendorId,
                    'reconciliation_account' => self::RECONCILIATION_ACCOUNT,
                    'payment_terms' => null,
                    'payment_methods' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($pivot !== []) {
                DB::table('company_vendor')->insertOrIgnore($pivot);
            }
        });
    }
}
