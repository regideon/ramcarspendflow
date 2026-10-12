<?php

namespace App\Models\Rfp;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vendor;
use Database\Factories\Rfp\RfpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Request for Payment.
 */
#[Fillable([
    'company_id', 'requestor_id', 'request_type', 'rfp_due_type_id', 'account_group_id', 'vendor_id',
    'payment_method_id', 'purpose', 'is_direct_to_vp', 'is_pre_approved', 'pre_approval_documents',
])]
class Rfp extends Model
{
    /** @use HasFactory<RfpFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'total_php_amount' => '0.00',
        'is_direct_to_vp' => false,
        'is_pre_approved' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (Rfp $rfp): void {
            if ($rfp->isDirty('rfp_due_type_id') || $rfp->due_date === null) {
                $rfp->due_date = $rfp->dueType?->dueDateFrom();
            }
        });

        static::created(function (Rfp $rfp): void {
            $rfp->rfp_number = sprintf('RFP-%s-%06d', $rfp->created_at->format('Y'), $rfp->id);
            $rfp->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    /**
     * @return BelongsTo<DueType, $this>
     */
    public function dueType(): BelongsTo
    {
        return $this->belongsTo(DueType::class, 'rfp_due_type_id');
    }

    /**
     * @return BelongsTo<AccountGroup, $this>
     */
    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class);
    }

    /**
     * The payee ("Final payee" on a Reimbursement, usually the employee's vendor account).
     *
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('sort');
    }

    /**
     * @return HasMany<History, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(History::class)->latest('id');
    }

    /**
     * The person who must act next, when one is assigned (otherwise anyone with current_approver_role).
     *
     * @return BelongsTo<User, $this>
     */
    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    /**
     * My Requests: only the user's own RFPs (as legacy "My RFPs", even for Admins).
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('requestor_id', $user->id);
    }

    /**
     * RFPs waiting for the user: assigned to them, or unassigned and waiting for one of their roles.
     */
    #[Scope]
    protected function awaiting(Builder $query, User $user): void
    {
        $query->whereNotIn('status', [RfpStatus::Draft, RfpStatus::ForRevision, ...RfpStatus::final()])
            ->where(fn (Builder $query) => $query
                ->where('current_approver_id', $user->id)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('current_approver_id')
                    ->whereIn('current_approver_role', $user->getRoleNames())));
    }

    /**
     * RFP Approvals: Admins and Accounting see every submitted RFP; business approvers
     * see what waits for them and what they have acted on.
     */
    #[Scope]
    protected function inApprovalsOf(Builder $query, User $user): void
    {
        $query->where('status', '!=', RfpStatus::Draft);

        if ($user->hasAnyRole([RoleName::Admin, ...RoleName::rfpAccounting()])) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->awaiting($user)
            ->orWhere('current_approver_id', $user->id)
            ->orWhereHas('histories', fn (Builder $query) => $query->where('user_id', $user->id)));
    }

    /**
     * Who the RFP is with now, for the queue: a person, a role, or the requestor.
     */
    public function currentApproverLabel(): ?string
    {
        if ($this->status === RfpStatus::ForRevision) {
            return 'Requestor';
        }

        if (! $this->status?->isAwaitingApproval()) {
            return null;
        }

        return $this->currentApprover?->name ?? $this->current_approver_role?->value;
    }

    /**
     * Capture today's rate to PHP on every line and recompute the PHP total.
     */
    public function refreshTotals(): void
    {
        $items = $this->items()->with('currency')->get();

        $items->each(fn (Item $item) => $item->capturePhpAmount()->saveQuietly());

        $this->forceFill([
            'total_php_amount' => $items->reduce(fn (string $sum, Item $item): string => bcadd($sum, $item->php_amount, 2), '0.00'),
        ])->saveQuietly();

        $this->setRelation('items', $items);
    }

    /**
     * Line amounts per foreign currency, e.g. ['USD' => '1200.00'].
     *
     * @return array<string, string>
     */
    public function foreignTotals(): array
    {
        return $this->items
            ->reject(fn (Item $item): bool => $item->currency?->code === 'PHP')
            ->groupBy(fn (Item $item): string => (string) $item->currency?->code)
            ->map(fn ($items): string => $items->reduce(fn (string $sum, Item $item): string => bcadd($sum, $item->amount, 2), '0.00'))
            ->all();
    }

    /**
     * Submit a draft to AP Processor review (Step 2).
     *
     * @throws ValidationException
     */
    public function submit(User $by): void
    {
        DB::transaction(function () use ($by): void {
            /** @var self $rfp */
            $rfp = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $rfp->refreshTotals();

            $missingRates = $rfp->items
                ->filter(fn (Item $item): bool => bccomp($item->exchange_rate, '0', 6) <= 0)
                ->map(fn (Item $item): string => (string) $item->currency?->code)
                ->unique()
                ->implode(', ');

            $errors = array_filter([
                'status' => $rfp->status === RfpStatus::Draft ? null : 'Only a draft can be submitted.',
                'rfp_due_type_id' => $rfp->rfp_due_type_id ? null : 'Choose a due type.',
                'items' => $rfp->items->isNotEmpty() ? null : 'Add at least one line item.',
                'total_php_amount' => bccomp($rfp->total_php_amount, '0', 2) > 0 ? null : 'The total amount must be greater than zero.',
                'attachments' => $rfp->items->every(fn (Item $item): bool => filled($item->attachments)) ? null : 'Attach supporting documents to every line.',
                'pre_approval_documents' => ! $rfp->is_pre_approved || filled($rfp->pre_approval_documents) ? null : 'Attach the proof of approval.',
                'exchange_rate' => $missingRates === '' ? null : "No exchange rate to PHP is on file for {$missingRates}.",
            ]);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $rfp->submitted_at = now();
            $rfp->current_approver_role = RoleName::ApProcessor; // Step 2: AP first review
            $rfp->due_date = $rfp->dueType->dueDateFrom($rfp->submitted_at); // counts from submission, not from the draft
            $rfp->transitionTo(RfpStatus::ForApReview, $by);

            $this->setRawAttributes($rfp->getAttributes(), sync: true);
        });
    }

    /**
     * Change status and record it in the history. Call inside a transaction.
     */
    public function transitionTo(RfpStatus $to, ?User $by, ?string $remarks = null): void
    {
        $from = $this->status;

        $this->status = $to;
        $this->save();

        $this->histories()->create([
            'user_id' => $by?->id,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => RfpRequestType::class,
            'status' => RfpStatus::class,
            'current_approver_role' => RoleName::class,
            'total_php_amount' => 'decimal:2',
            'due_date' => 'date',
            'is_direct_to_vp' => 'boolean',
            'is_pre_approved' => 'boolean',
            'pre_approval_documents' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
