<?php

namespace App\Models\P2p;

use App\Models\Company;
use App\Models\Vendor;
use Database\Factories\P2p\SapGoodReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tier 1 — SAP goods-receipt header mirror (read-only).
 */
#[Table('p2p_sap_good_receipts')]
#[Fillable([
    'purchase_order', 'company_id', 'vendor_id', 'good_receipt_number', 'reference_number',
    'amount_total', 'due_at', 'upload_at', 'date_at', 'delivery_type_id',
])]
class SapGoodReceipt extends Model
{
    /** @use HasFactory<SapGoodReceiptFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<SapPurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(SapPurchaseOrder::class, 'purchase_order', 'purchase_order');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<SapGoodReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SapGoodReceiptItem::class, 'p2p_sap_good_receipt_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_total' => 'decimal:4',
            'due_at' => 'date',
            'upload_at' => 'date',
            'date_at' => 'date',
        ];
    }
}
