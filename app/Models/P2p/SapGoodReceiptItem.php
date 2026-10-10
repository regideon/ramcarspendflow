<?php

namespace App\Models\P2p;

use Database\Factories\P2p\SapGoodReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tier 1 — SAP goods-receipt line mirror (read-only). Billed by p2p_invoice_lines.
 */
#[Table('p2p_sap_good_receipt_items')]
#[Fillable([
    'p2p_sap_good_receipt_id', 'purchase_order', 'purchase_order_item', 'item_numbering', 'item_id',
    'material_doc_year', 'material_doc_item', 'storage_location', 'item_name', 'item_description',
    'quantity_po', 'quantity_served', 'uom', 'gr', 'unit_cost', 'amount', 'quantity_po_balance',
])]
class SapGoodReceiptItem extends Model
{
    /** @use HasFactory<SapGoodReceiptItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<SapGoodReceipt, $this>
     */
    public function goodReceipt(): BelongsTo
    {
        return $this->belongsTo(SapGoodReceipt::class, 'p2p_sap_good_receipt_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'p2p_sap_good_receipt_item_id');
    }

    /**
     * Quantity already billed across all invoice lines.
     */
    public function quantityBilled(): string
    {
        return (string) $this->invoiceLines()->sum('quantity_billed');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_po' => 'decimal:3',
            'quantity_served' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'quantity_po_balance' => 'decimal:3',
        ];
    }
}
