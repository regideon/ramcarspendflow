<?php

namespace App\Models\P2p;

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tier 2 — derived per-GR projection. Never edited by hand: P2pGoodReceiptStatusService
 * recomputes status and quantities from invoice lines and escalations (single writer).
 */
#[Table('p2p_good_receipt_states')]
#[Fillable([
    'p2p_sap_good_receipt_id', 'purchase_order', 'good_receipt_number', 'company_id', 'vendor_id',
    'status', 'quantity_total', 'quantity_served', 'amount_total', 'is_fully_processed',
    'merged_at', 'merged_into_good_receipt_number',
    'reversed_at', 'reversal_document', 'replacement_gr_number',
])]
class GoodReceiptState extends Model
{
    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => P2pStatus::Pending,
    ];

    /**
     * @return BelongsTo<SapGoodReceipt, $this>
     */
    public function sapGoodReceipt(): BelongsTo
    {
        return $this->belongsTo(SapGoodReceipt::class, 'p2p_sap_good_receipt_id');
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
     * GRs a vendor may act on.
     */
    #[Scope]
    protected function billable(Builder $query): void
    {
        $query->whereIn('status', [P2pStatus::Pending->value, P2pStatus::ReturnedToVendor->value]);
    }

    /**
     * Hide GRs whose SAP receipt has no remaining items. Legacy/projection-only
     * states without a mirror receipt stay visible so older data and tests are not
     * hidden.
     */
    #[Scope]
    protected function withBillableItems(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('p2p_sap_good_receipt_id')
                ->orWhereHas('sapGoodReceipt.items', function (Builder $items): void {
                    $items->whereNull('merged_at')->where('quantity_served', '>', 0);
                });
        });
    }

    /**
     * Whether this GR still owns items that can be processed.
     */
    public function hasBillableItems(): bool
    {
        if ($this->p2p_sap_good_receipt_id === null) {
            return true;
        }

        return $this->sapGoodReceipt?->items()
            ->whereNull('merged_at')
            ->where('quantity_served', '>', 0)
            ->exists() ?? false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => P2pStatus::class,
            'quantity_total' => 'integer',
            'quantity_served' => 'integer',
            'amount_total' => 'decimal:2',
            'is_fully_processed' => 'boolean',
            'merged_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
