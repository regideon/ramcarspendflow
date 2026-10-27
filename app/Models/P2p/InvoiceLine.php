<?php

namespace App\Models\P2p;

use App\Enums\P2pThreeWayMatchStatus;
use App\Models\User;
use Database\Factories\P2p\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tier 2 — a billed line: reference to a SAP GR item + the quantity billed.
 * Remaining = mirror qty − Σ quantity_billed.
 */
#[Table('p2p_invoice_lines')]
#[Fillable([
    'p2p_invoice_id', 'p2p_sap_good_receipt_item_id', 'purchase_order', 'purchase_order_item',
    'item_id', 'storage_location', 'p2p_material_classification_id', 'classified_by', 'classified_at', 'item_name',
    'item_description', 'quantity_po', 'quantity_billed', 'quantity_previous', 'quantity_remaining',
    'is_fully_billed', 'uom', 'unit_cost', 'amount', 'invoice_reference', 'invoice_unit_price', 'invoice_currency',
    'match_status', 'match_reasons', 'is_invoice_verified', 'is_quantity_edited',
])]
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'p2p_invoice_id');
    }

    /**
     * @return BelongsTo<SapGoodReceiptItem, $this>
     */
    public function sapItem(): BelongsTo
    {
        return $this->belongsTo(SapGoodReceiptItem::class, 'p2p_sap_good_receipt_item_id');
    }

    /**
     * @return BelongsTo<MaterialClassification, $this>
     */
    public function materialClassification(): BelongsTo
    {
        return $this->belongsTo(MaterialClassification::class, 'p2p_material_classification_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    /**
     * Whether AP should check this line: it does not match the invoice, was not checked
     * against one, or the vendor changed the quantity read from it.
     */
    public function needsMatchReview(): bool
    {
        return $this->match_status === P2pThreeWayMatchStatus::NoMatch
            || ($this->match_status !== null && ! $this->is_invoice_verified)
            || $this->is_quantity_edited;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_po' => 'integer',
            'quantity_billed' => 'integer',
            'quantity_previous' => 'decimal:2',
            'quantity_remaining' => 'decimal:2',
            'is_fully_billed' => 'boolean',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'classified_at' => 'datetime',
            'invoice_unit_price' => 'decimal:4',
            'match_status' => P2pThreeWayMatchStatus::class,
            'match_reasons' => 'array',
            'is_invoice_verified' => 'boolean',
            'is_quantity_edited' => 'boolean',
        ];
    }
}
