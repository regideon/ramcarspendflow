<?php

namespace App\Models\P2p;

use App\Models\User;
use Database\Factories\P2p\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tier 2 — a billed line: reference to a SAP GR item + the quantity billed. Replaces
 * the legacy `-P` GR row cloning; remaining = mirror qty − Σ quantity_billed.
 */
#[Table('p2p_invoice_lines')]
#[Fillable([
    'p2p_invoice_id', 'p2p_sap_good_receipt_item_id', 'purchase_order', 'purchase_order_item',
    'item_id', 'storage_location', 'p2p_material_classification_id', 'classified_by', 'classified_at', 'item_name',
    'item_description', 'quantity_po', 'quantity_billed', 'quantity_previous', 'quantity_remaining',
    'is_fully_billed', 'uom', 'unit_cost', 'amount',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_po' => 'decimal:3',
            'quantity_billed' => 'decimal:3',
            'quantity_previous' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'is_fully_billed' => 'boolean',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'classified_at' => 'datetime',
        ];
    }
}
