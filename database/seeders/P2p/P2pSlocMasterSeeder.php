<?php

namespace Database\Seeders\P2p;

use App\Models\P2p\MaterialClassification;
use App\Models\P2p\SlocClassification;
use Illuminate\Database\Seeder;

/**
 * Seeds the storage-location master (`p2p_sloc_classifications`) from the T001L
 * extract in `database/data/T001L_SLOC.csv` (the SAP storage-location master:
 * company + plant + SLOC + description).
 *
 * A SLOC resolves to its configured classification (`config/p2p.php`) or the
 * default `unclassified` bucket that AP Finance classifies later. Existing rows
 * are never re-classified — only the description is refreshed — so a manual
 * classification survives a re-seed. Ported from the legacy P2P SlocMasterSeeder.
 */
class P2pSlocMasterSeeder extends Seeder
{
    public function run(): void
    {
        $classificationIds = MaterialClassification::query()->pluck('id', 'code')->all();

        if ($classificationIds === []) {
            return; // classifications must exist first (P2pDemoMasterDataSeeder)
        }

        $defaultCode = (string) config('p2p.default_type', 'unclassified');
        $map = (array) config('p2p.storage_location_types', []);

        $handle = fopen(database_path('data/T001L_SLOC.csv'), 'r');

        if ($handle === false) {
            return;
        }

        $heading = true;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if ($heading) {
                $heading = false;

                continue;
            }

            // Columns: Company, Company Code, Plant Code, SLOC Code, SLOC Description.
            $companyCode = $this->clean($row[1] ?? null);
            $plant = $this->clean($row[2] ?? null);
            $storageLocation = strtoupper((string) $this->clean($row[3] ?? null));
            $description = $this->clean($row[4] ?? null);

            if ($storageLocation === '') {
                continue;
            }

            $existing = SlocClassification::query()
                ->where('company_code', $companyCode)
                ->where('plant', $plant)
                ->where('storage_location', $storageLocation)
                ->first();

            // A manual classification is authoritative: only refresh the label.
            if ($existing !== null) {
                $existing->update(['description' => $description]);

                continue;
            }

            $code = (string) ($map[$storageLocation] ?? $defaultCode);
            $classificationId = $classificationIds[$code] ?? $classificationIds[$defaultCode] ?? null;

            if ($classificationId === null) {
                continue;
            }

            SlocClassification::create([
                'company_code' => $companyCode,
                'plant' => $plant,
                'storage_location' => $storageLocation,
                'description' => $description,
                'p2p_material_classification_id' => $classificationId,
                'is_active' => true,
            ]);
        }

        fclose($handle);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
