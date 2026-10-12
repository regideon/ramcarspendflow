<?php

namespace App\Models\Rfp;

use Database\Factories\Rfp\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expense category shown on the RFP line item (e.g. Benefits, Repair & maintenance).
 */
#[Table('rfp_categories')]
#[Fillable(['name', 'is_default', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Only one category is pre-selected on new RFP lines
        static::saved(function (Category $category): void {
            if ($category->is_default && ($category->wasRecentlyCreated || $category->wasChanged('is_default'))) {
                static::query()->whereKeyNot($category->getKey())->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return HasMany<Subcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class, 'rfp_category_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
