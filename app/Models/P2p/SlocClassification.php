<?php

namespace App\Models\P2p;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Storage-location (SLOC) → material-classification mapping. A row with null
 * company/plant is the global default. Manual classifications survive re-seeding.
 */
#[Table('p2p_sloc_classifications')]
#[Fillable(['company_code', 'plant', 'storage_location', 'description', 'p2p_material_classification_id', 'is_active'])]
class SlocClassification extends Model
{
    /**
     * @return BelongsTo<MaterialClassification, $this>
     */
    public function materialClassification(): BelongsTo
    {
        return $this->belongsTo(MaterialClassification::class, 'p2p_material_classification_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
