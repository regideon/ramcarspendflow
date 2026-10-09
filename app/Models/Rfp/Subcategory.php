<?php

namespace App\Models\Rfp;

use App\Models\GlAccount;
use Database\Factories\Rfp\SubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expense subcategory: decides the G/L account and SAP posting data for an RFP line.
 */
#[Table('rfp_subcategories')]
#[Fillable(['rfp_category_id', 'gl_account_id', 'name', 'document_type', 'posting_key', 'requires_cost_center', 'requires_po_number', 'is_active'])]
class Subcategory extends Model
{
    /** @use HasFactory<SubcategoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'rfp_category_id');
    }

    /**
     * @return BelongsTo<GlAccount, $this>
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_cost_center' => 'boolean',
            'requires_po_number' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
