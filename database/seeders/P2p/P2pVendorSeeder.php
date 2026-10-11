<?php

namespace Database\Seeders\P2p;

use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports the SAP vendor master from the live extract `database/data/P2pVendors.csv`
 * (columns: code, name, email, country, enabled) and links vendors to companies via
 * `company_vendor` using the company/vendor extensions in `database/data/LocalVendor.csv`
 * (columns: CoCd, Company, Supplier, Name, Group Description).
 *
 * Vendors are upserted by SAP code (LIFNR); richer fields are left for SAP sync or
 * manual maintenance. Re-runnable, streamed in chunks.
 */
class P2pVendorSeeder extends Seeder
{
    /** Reconciliation account (AKONT) placeholder for imported vendors. */
    private const string RECONCILIATION_ACCOUNT = '2000000';

    private const int CHUNK = 500;

    public function run(): void
    {
        $this->importVendors();
        $this->linkCompanies();
    }

    private function importVendors(): void
    {
        $handle = fopen(database_path('data/P2pVendors.csv'), 'r');

        if ($handle === false) {
            return;
        }

        $heading = true;
        $buffer = [];

        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if ($heading) {
                $heading = false;

                continue;
            }

            $code = trim((string) ($row[0] ?? ''));

            if ($code === '' || mb_strlen($code) > 10) {
                continue; // skip empty / malformed codes (the vendors.code column is 10)
            }

            $name = trim((string) ($row[1] ?? ''));
            $now = now();

            $buffer[] = [
                'code' => $code,
                'name' => $name !== '' ? mb_substr($name, 0, 150) : $code,
                'email' => ($row[2] ?? '') !== '' ? $row[2] : null,
                'country' => ($row[3] ?? '') !== '' ? $row[3] : 'PH',
                'is_active' => (bool) ($row[4] ?? '1'),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($buffer) >= self::CHUNK) {
                $this->upsertVendors($buffer);
                $buffer = [];
            }
        }

        fclose($handle);

        $this->upsertVendors($buffer);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertVendors(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        Vendor::query()->upsert($rows, ['code'], ['name', 'email', 'country', 'is_active', 'updated_at']);
    }

    /**
     * Link vendors to their companies using the legacy company/vendor extensions.
     */
    private function linkCompanies(): void
    {
        $handle = fopen(database_path('data/LocalVendor.csv'), 'r');

        if ($handle === false) {
            return;
        }

        $companyIdsByCode = Company::query()->pluck('id', 'code');
        $heading = true;
        $links = [];

        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if ($heading) {
                $heading = false;

                continue;
            }

            $companyCode = trim((string) ($row[0] ?? ''));
            $code = trim((string) ($row[2] ?? ''));

            if ($code === '' || $companyCode === '' || ! $companyIdsByCode->has($companyCode)) {
                continue;
            }

            $links[$companyCode.'|'.$code] = ['company' => $companyCode, 'vendor' => $code];

            if (count($links) >= self::CHUNK) {
                $this->insertLinks($links, $companyIdsByCode);
                $links = [];
            }
        }

        fclose($handle);

        $this->insertLinks($links, $companyIdsByCode);
    }

    /**
     * @param  array<string, array{company: string, vendor: string}>  $links
     * @param  Collection<string, int>  $companyIdsByCode
     */
    private function insertLinks(array $links, Collection $companyIdsByCode): void
    {
        if ($links === []) {
            return;
        }

        $vendorIdsByCode = Vendor::query()
            ->whereIn('code', array_column($links, 'vendor'))
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
    }
}
