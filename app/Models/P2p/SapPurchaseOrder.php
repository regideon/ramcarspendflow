<?php

namespace App\Models\P2p;

use App\Enums\P2pDownPaymentStatus;
use App\Enums\P2pPurchaseOrderStatus;
use App\Models\Company;
use App\Models\Vendor;
use Database\Factories\P2p\SapPurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tier 1 — SAP purchase-order mirror (read-only). SAP is the source of truth.
 */
#[Table('p2p_sap_purchase_orders')]
#[Fillable([
    'status_id', 'company_code', 'purchase_order', 'purchase_order_item', 'vendor_code',
    'purchase_order_date', 'purchase_order_type', 'changed_on', 'created_by', 'currency_key',
    'total_amount', 'gr_numbers', 'quantity_po_balance', 'has_down_payment',
    'down_payment_amount', 'down_payment_percent', 'down_payment_type', 'down_payment_due_date',
    'down_payment_status', 'down_payment_document', 'down_payment_paid_at',
])]
class SapPurchaseOrder extends Model
{
    /** @use HasFactory<SapPurchaseOrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_id' => 0,
    ];

    /**
     * @return HasMany<SapGoodReceipt, $this>
     */
    public function goodReceipts(): HasMany
    {
        return $this->hasMany(SapGoodReceipt::class, 'purchase_order', 'purchase_order');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_code', 'code');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_code', 'code');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status_id', P2pPurchaseOrderStatus::Pending->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_id' => P2pPurchaseOrderStatus::class,
            'purchase_order_date' => 'date',
            'changed_on' => 'date',
            'total_amount' => 'decimal:2',
            'gr_numbers' => 'array',
            'quantity_po_balance' => 'integer',
            'has_down_payment' => 'boolean',
            'down_payment_amount' => 'decimal:2',
            'down_payment_percent' => 'decimal:2',
            'down_payment_due_date' => 'date',
            'down_payment_status' => P2pDownPaymentStatus::class,
            'down_payment_paid_at' => 'datetime',
        ];
    }
}
