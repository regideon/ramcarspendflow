<?php

namespace App\Services\P2p;

use App\Models\P2p\InvoiceLine;
use App\Models\P2p\MaterialClassification;
use App\Models\P2p\SlocClassification;
use App\Models\User;

/**
 * Resolves a goods-receipt line's storage location (SAP MSEG `LGORT`) to its
 * material classification, or the default `unclassified` bucket for AP Finance to
 * classify later. Supports a manual override (who + when), which a re-seed never
 * overwrites. See docs/p2p/03-master-data.md §3.4.
 */
class P2pMaterialClassificationService
{
    /**
     * Most specific active mapping wins: company+plant, then company, then global.
     */
    public function resolve(?string $storageLocation, ?string $companyCode = null, ?string $plant = null): ?MaterialClassification
    {
        if ($storageLocation === null || $storageLocation === '') {
            return $this->defaultClassification();
        }

        $mapping = SlocClassification::query()
            ->where('storage_location', $storageLocation)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('company_code')->orWhere('company_code', $companyCode))
            ->where(fn ($query) => $query->whereNull('plant')->orWhere('plant', $plant))
            // Non-null company/plant sort first (specific over global).
            ->orderByRaw('company_code IS NULL')
            ->orderByRaw('plant IS NULL')
            ->first();

        return $mapping?->materialClassification ?? $this->defaultClassification();
    }

    /**
     * Classify a line (manual override), recording who did it.
     */
    public function classify(InvoiceLine $line, MaterialClassification $classification, User $by): void
    {
        $line->forceFill([
            'p2p_material_classification_id' => $classification->id,
            'classified_by' => $by->id,
            'classified_at' => now(),
        ])->save();
    }

    public function defaultClassification(): ?MaterialClassification
    {
        return MaterialClassification::query()
            ->where('code', (string) config('p2p.default_type', 'unclassified'))
            ->first();
    }
}
