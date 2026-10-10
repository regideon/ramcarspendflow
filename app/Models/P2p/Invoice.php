<?php

namespace App\Models\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use Database\Factories\P2p\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tier 2 — the P2P submission (legacy `good_receipt_headers`). Owns the authoritative
 * workflow status; every transition writes a p2p_approval_histories row.
 */
#[Table('p2p_invoices')]
#[Fillable([
    'status', 'grc_number', 'code', 'user_id', 'vendor_id', 'company_id', 'po_numbers',
    'gr_numbers', 'reference_number', 'clearing_doc_no', 'fi_doc_no', 'invoice_number',
    'quantity_submitted', 'amount_submitted', 'processed', 'completed_at', 'withholding_tax_code',
    'withholding_tax_base', 'tax_code', 'remarks_ap', 'remarks_checker', 'invoice_at', 'fiscal_year',
    'attachments', 'submitted_at', 'escalated_at', 'warning_sent_at', 'upload_at', 'updated_by',
    'vatable_sales', 'vat_exempt', 'vat_zero_rated', 'vat', 'total', 'ewt', 'net',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => P2pStatus::Pending,
    ];

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice): void {
            $invoice->grc_number = sprintf('GRC-%08d', $invoice->id);
            $invoice->code = Str::upper(Str::random(8));
            $invoice->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'p2p_invoice_id');
    }

    /**
     * @return HasMany<ApprovalHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'p2p_invoice_id')->latest('id');
    }

    /**
     * @return HasMany<Escalation, $this>
     */
    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class, 'p2p_invoice_id');
    }

    /**
     * Invoices a user may see: Admins see all, a Vendor their own, AP/Procurement/Warehouse
     * see the worklist for their companies.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(RoleName::Admin)) {
            return;
        }

        if ($user->hasRole(RoleName::Vendor)) {
            $query->where('user_id', $user->id);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => P2pStatus::class,
            'po_numbers' => 'array',
            'gr_numbers' => 'array',
            'reference_number' => 'array',
            'attachments' => 'array',
            'quantity_submitted' => 'decimal:2',
            'amount_submitted' => 'decimal:2',
            'withholding_tax_base' => 'decimal:2',
            'vatable_sales' => 'decimal:2',
            'vat_exempt' => 'decimal:2',
            'vat_zero_rated' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'ewt' => 'decimal:2',
            'net' => 'decimal:2',
            'processed' => 'boolean',
            'completed_at' => 'datetime',
            'invoice_at' => 'datetime',
            'submitted_at' => 'datetime',
            'escalated_at' => 'datetime',
            'warning_sent_at' => 'datetime',
            'upload_at' => 'date',
        ];
    }
}
