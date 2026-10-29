<?php

namespace App\Models\Rfp;

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\RfpResource;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Concerns\HasComments;
use App\Models\P2p\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Rfp\ApprovalEngine;
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
use Illuminate\Support\Str;
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
    use HasComments, HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'total_php_amount' => '0.00',
        'is_direct_to_vp' => false,
        'is_pre_approved' => false,
        'is_ids' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (Rfp $rfp): void {
            if ($rfp->isDirty('rfp_due_type_id') || $rfp->due_date === null) {
                $rfp->due_date = $rfp->dueType?->dueDateFrom();
            }

            // The signed-in user, unless the caller already set who acted (e.g. transitionTo)
            if (! $rfp->isDirty('updated_by_id') && auth()->id() !== null) {
                $rfp->updated_by_id = auth()->id();
            }
        });

        static::creating(function (Rfp $rfp): void {
            $rfp->created_by_id ??= auth()->id() ?? $rfp->requestor_id;
            $rfp->updated_by_id ??= $rfp->created_by_id;
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
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
     * P2P requests that point at this RFP (e.g. the invoice that settles a down payment).
     *
     * @return HasMany<Invoice, $this>
     */
    public function p2pInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'rfp_id');
     * Relations the RFP view pages show (header, items, tax entries, history, route),
     * loaded up front so the page runs no query per line.
     *
     * @var list<string>
     */
    public const array VIEW_RELATIONS = [
        'company', 'requestor', 'dueType', 'accountGroup', 'vendor', 'paymentMethod',
        'items.currency', 'items.subcategory', 'items.glAccount', 'items.costCenter',
        'items.company', 'items.vendor', 'items.oneTimeVendor',
        'items.taxCode', 'items.withholdingTaxCode', 'items.withholdingTaxType',
        'histories.user', 'approvalSteps',
    ];

    /**
     * Reload the RFP and the relations it had loaded, nested ones included (a plain
     * refresh() reloads only the top level, so a view page would then query per line).
     */
    public function reloadWithRelations(): void
    {
        $loaded = array_keys($this->getRelations());

        $this->setRawAttributes(static::withTrashed()->findOrFail($this->getKey())->getAttributes(), sync: true);
        $this->setRelations([]);

        $this->load([
            ...array_filter(self::VIEW_RELATIONS, fn (string $relation): bool => in_array(Str::before($relation, '.'), $loaded, true)),
            ...array_diff($loaded, array_map(fn (string $relation): string => Str::before($relation, '.'), self::VIEW_RELATIONS)),
        ]);
    }

    /**
     * @return HasMany<History, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(History::class)->latest('id');
    }

    /**
     * Users who can open this RFP and so may be @mentioned on it: the requestor and the
     * RFP Approvals roles (business approvers have no RFP screen yet).
     *
     * @return Builder<User>
     */
    public function mentionableUsers(): Builder
    {
        return User::query()->where('is_active', true)->where(fn (Builder $query) => $query
            ->whereKey($this->requestor_id)
            ->orWhereHas('roles', fn (Builder $query) => $query->whereIn('name', RoleName::rfpApprovalsUsers())));
    }

    /**
     * The requestor sees their own RFPs; Admin and AP see all (as My Requests and RFP Approvals).
     *
     * @param  Builder<Rfp>  $query
     */
    public function scopeCommentableBy(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('0 = 1');
        } elseif (! $user->hasAnyRole(RoleName::rfpApprovalsUsers())) {
            $query->where('requestor_id', $user->id);
        }
    }

    public static function commentTypeLabel(): string
    {
        return 'RFP';
    }

    public function commentSubject(): string
    {
        return $this->rfp_number;
    }

    /**
     * The requestor opens it under My Requests; AP and Admin under RFP Approvals.
     */
    public function commentUrlFor(User $user): string
    {
        return $user->id === $this->requestor_id || ! $user->hasAnyRole(RoleName::rfpApprovalsUsers())
            ? RfpResource::getUrl('view', ['record' => $this])
            : RfpApprovalResource::getUrl('view', ['record' => $this]);
    }

    /**
     * The RFP's own approval route, across every round.
     *
     * @return HasMany<ApprovalStep, $this>
     */
    public function approvalSteps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('round')->orderBy('sequence')->orderBy('id');
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
     * RFPs waiting for the user: assigned to them, or unassigned and waiting for a role
     * they hold in the RFP's company (including any role of a parallel matrix step).
     */
    #[Scope]
    protected function awaiting(Builder $query, User $user): void
    {
        $rolesHeldInRfpCompany = fn () => CompanyRole::query()
            ->select('role')
            ->where('user_id', $user->id)
            ->whereColumn('company_role_user.company_id', 'rfps.company_id');

        $query->whereNotIn('status', [RfpStatus::Draft, RfpStatus::ForRevision, ...RfpStatus::final()])
            ->where(fn (Builder $query) => $query
                ->where('current_approver_id', $user->id)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('current_approver_id')
                    ->where(fn (Builder $query) => $query
                        ->whereIn('current_approver_role', $rolesHeldInRfpCompany())
                        ->orWhereHas('approvalSteps', fn (Builder $query) => $query
                            ->where('state', RfpApprovalStepState::Active)
                            ->whereIn('role', $rolesHeldInRfpCompany())))));
    }

    /**
     * RFP Approvals: Admins see every submitted RFP, Accounting those of the companies they
     * hold a role in; business approvers see what waits for them and what they have acted on.
     */
    #[Scope]
    protected function inApprovalsOf(Builder $query, User $user): void
    {
        $query->where('status', '!=', RfpStatus::Draft);

        if ($user->hasRole(RoleName::Admin)) {
            return;
        }

        if ($user->hasAnyRole(RoleName::rfpAccounting())) {
            $query->whereIn('company_id', $user->companyRoles()->select('company_id'));

            return;
        }

        $query->where(fn (Builder $query) => $query
            ->awaiting($user)
            ->orWhere('current_approver_id', $user->id)
            ->orWhereHas('histories', fn (Builder $query) => $query->where('user_id', $user->id)));
    }

    /**
     * "overdue" or "today" while the RFP is still with an approver or Accounting; null otherwise.
     */
    public function dueState(): ?string
    {
        if (! $this->due_date || ! $this->status?->isAwaitingApproval()) {
            return null;
        }

        return match (true) {
            $this->due_date->isBefore(today()) => 'overdue',
            $this->due_date->isToday() => 'today',
            default => null,
        };
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
     * Submit a draft, or resubmit a revised RFP, into its approval route.
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
                'status' => $rfp->status->isEditableByRequestor() ? null : 'Only a draft or a returned request can be submitted.',
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

            $engine = app(ApprovalEngine::class);

            if ($rfp->status === RfpStatus::ForRevision) {
                $engine->resubmit($rfp, $by);
            } else {
                $rfp->submitted_at = now();
                $rfp->due_date = $rfp->dueType->dueDateFrom($rfp->submitted_at); // counts from submission, not from the draft
                $engine->start($rfp, $by);
            }

            $this->setRawAttributes($rfp->getAttributes(), sync: true);
        });
    }

    /**
     * Change status and record it in the history. Call inside a transaction.
     */
    public function transitionTo(RfpStatus $to, ?User $by, ?string $remarks = null, ?string $action = null): void
    {
        $from = $this->status;

        $this->status = $to;
        $this->updated_by_id = $by?->id ?? $this->updated_by_id;
        $this->save();

        $this->histories()->create([
            'user_id' => $by?->id,
            'action' => $action,
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
            'is_ids' => 'boolean',
            'pre_approval_documents' => 'array',
            'submitted_at' => 'datetime',
            'approval_round' => 'integer',
            'routed_php_amount' => 'decimal:2',
        ];
    }
}
