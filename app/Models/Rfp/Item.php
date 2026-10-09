<?php

namespace App\Models\Rfp;

use App\Enums\RfpRequestType;
use App\Enums\RfpVendorType;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\GlAccount;
use App\Models\Vendor;
use Database\Factories\Rfp\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RFP line item, in its own currency. The G/L account is copied from the subcategory
 * and the rate to PHP is captured on save.
 */
#[Table('rfp_items')]
#[Fillable([
    'rfp_id', 'sort', 'company_id', 'vendor_type', 'vendor_id', 'rfp_one_time_vendor_id', 'rfp_subcategory_id',
    'cost_center_id', 'invoice_number', 'invoice_date', 'po_number', 'description', 'currency_id', 'amount', 'attachments',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'exchange_rate' => '0.000000',
        'php_amount' => '0.00',
    ];

    protected static function booted(): void
    {
        static::saving(function (Item $item): void {
            if ($item->isDirty('rfp_subcategory_id') || $item->gl_account_id === null) {
                $item->gl_account_id = Subcategory::query()->whereKey($item->rfp_subcategory_id)->value('gl_account_id');
            }

            // Who was paid is only kept on Reimbursement lines
            if ($item->rfp?->request_type !== RfpRequestType::Reimbursement) {
                $item->forceFill(['company_id' => null, 'vendor_type' => null, 'vendor_id' => null, 'rfp_one_time_vendor_id' => null]);
            }

            if ($item->isDirty(['currency_id', 'amount'])) {
                $item->capturePhpAmount();
            }
        });
    }

    /**
     * Capture today's rate to PHP and the PHP amount. A rate of 0 means none is on file.
     */
    public function capturePhpAmount(): static
    {
        $currency = $this->relationLoaded('currency') && $this->currency?->getKey() === (int) $this->currency_id
            ? $this->currency
            : $this->setRelation('currency', Currency::find($this->currency_id))->currency;

        $rate = ($currency ? ExchangeRate::rateToPhp($currency) : null) ?? '0';

        $this->exchange_rate = $rate;
        $this->php_amount = bcadd(bcmul((string) $this->amount, $rate, 6), '0.005', 2); // round half up

        return $this;
    }

    /**
     * @return BelongsTo<Rfp, $this>
     */
    public function rfp(): BelongsTo
    {
        return $this->belongsTo(Rfp::class);
    }

    /**
     * Company of the vendor the requestor paid (Reimbursement only).
     *
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
     * @return BelongsTo<OneTimeVendor, $this>
     */
    public function oneTimeVendor(): BelongsTo
    {
        return $this->belongsTo(OneTimeVendor::class, 'rfp_one_time_vendor_id');
    }

    /**
     * @return BelongsTo<Subcategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class, 'rfp_subcategory_id');
    }

    /**
     * @return BelongsTo<GlAccount, $this>
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Name of the vendor the requestor paid, on a Reimbursement line.
     */
    public function paidToName(): ?string
    {
        return match ($this->vendor_type) {
            RfpVendorType::Vendor => $this->vendor?->name,
            RfpVendorType::OneTimeVendor => $this->oneTimeVendor?->name,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'vendor_type' => RfpVendorType::class,
            'invoice_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'php_amount' => 'decimal:2',
            'attachments' => 'array',
        ];
    }
}
