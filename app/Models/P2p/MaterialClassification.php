<?php

namespace App\Models\P2p;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Data-driven material classification (Raw Materials, Spare Parts, Unclassified…).
 * `role_name` is the Spatie role that owns warehouse escalations for the bucket.
 */
#[Table('p2p_material_classifications')]
#[Fillable(['code', 'name', 'color', 'role_name', 'sort', 'is_active'])]
class MaterialClassification extends Model
{
    /**
     * @return HasMany<SlocClassification, $this>
     */
    public function slocClassifications(): HasMany
    {
        return $this->hasMany(SlocClassification::class, 'p2p_material_classification_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'p2p_material_classification_user');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
