<?php

namespace App\Models\P2p;

use App\Enums\P2pStatus;
use App\Models\User;
use App\Services\P2p\Escalation\StepAction;
use App\Services\P2p\Escalation\StepActionRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * An escalation raised against a GR (one row per GR, sharing a batch_number).
 */
#[Table('p2p_escalations')]
#[Fillable([
    'escalation_number', 'batch_number', 'p2p_invoice_id', 'p2p_good_receipt_state_id',
    'p2p_escalation_reason_id', 'current_step_order', 'current_role', 'origin_role', 'status',
    'from_status', 'remarks', 'actioned_by', 'escalated_at', 'resolved_at',
])]
class Escalation extends Model
{
    /** Status values of an escalation request. */
    public const string STATUS_OPEN = 'open';

    public const string STATUS_RESOLVED = 'resolved';

    public const string STATUS_CANCELLED = 'cancelled';

    /**
     * Maps an escalation hop role to the application role that handles it. The portal
     * calls its vendors "supplier", but a route (and the client) talks about "vendor".
     */
    public const array ROLE_APP_ROLES = [
        'vendor' => 'supplier',
        'processor' => 'processor',
        'procurement' => 'procurement',
        'warehouse' => 'warehouse',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'p2p_invoice_id');
    }

    /**
     * @return BelongsTo<GoodReceiptState, $this>
     */
    public function goodReceiptState(): BelongsTo
    {
        return $this->belongsTo(GoodReceiptState::class, 'p2p_good_receipt_state_id');
    }

    /**
     * @return BelongsTo<EscalationReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(EscalationReason::class, 'p2p_escalation_reason_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    /**
     * The current hop of the escalation's route, if any.
     */
    public function currentStep(): ?EscalationReasonStep
    {
        if ($this->relationLoaded('reason')) {
            return $this->reason?->steps->firstWhere('step_order', $this->current_step_order);
        }

        return $this->reason()
            ->with('steps')
            ->first()
            ?->steps
            ->firstWhere('step_order', $this->current_step_order);
    }

    /**
     * The hop the escalation would move to next, or null at the final hop.
     */
    public function nextStep(): ?EscalationReasonStep
    {
        return $this->orderedSteps()
            ->first(fn (EscalationReasonStep $step): bool => $step->step_order > $this->current_step_order);
    }

    /**
     * The hop just before the current one, or null at the first hop (where the
     * previous handler is the originator).
     */
    public function previousStep(): ?EscalationReasonStep
    {
        return $this->orderedSteps()
            ->last(fn (EscalationReasonStep $step): bool => $step->step_order < $this->current_step_order);
    }

    /**
     * The configured label for the current hop's button, if any.
     */
    public function currentActionLabel(): ?string
    {
        $label = $this->currentStep()?->action_label;

        return filled($label) ? (string) $label : null;
    }

    /**
     * The custom step action configured on the current hop, if any. Replaces the
     * generic "Advance" when set.
     */
    public function currentStepAction(): ?StepAction
    {
        return app(StepActionRegistry::class)->resolve($this->currentStep()?->action);
    }

    /**
     * The role a "Return" would hand the escalation back to: the previous hop, or
     * the originator when it is already at its first hop.
     */
    public function previousHopRole(): ?string
    {
        $previous = $this->previousStep();

        if ($previous === null) {
            return $this->origin_role;
        }

        return $previous->role === EscalationReasonStep::ROLE_ORIGIN
            ? $this->origin_role
            : $previous->role;
    }

    /**
     * Label for where a "Return" would send the escalation, e.g. "Vendor".
     */
    public function previousRoleLabel(): string
    {
        return self::roleLabel($this->previousHopRole());
    }

    /**
     * @return Collection<int, EscalationReasonStep>
     */
    protected function orderedSteps(): Collection
    {
        $this->loadMissing('reason.steps');

        return ($this->reason?->steps ?? collect())->sortBy('step_order')->values();
    }

    /**
     * The escalation reference shown to users: the batch when the request spans
     * several GRs, otherwise the escalation's own number.
     */
    public function requestNumber(): string
    {
        return $this->batch_number ?: $this->escalation_number;
    }

    /**
     * Human-readable current hop, e.g. "Warehouse - Quantity Difference".
     */
    public function stepLabel(): string
    {
        return trim(implode(' - ', array_filter([self::roleLabel($this->current_role), $this->reason?->name])));
    }

    /**
     * Map a hop role to a human-readable label.
     */
    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            'warehouse' => 'Warehouse',
            'procurement' => 'Procurement',
            'vendor' => 'Vendor',
            'processor' => 'AP Processor',
            default => (string) $role,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_step_order' => 'integer',
            'from_status' => P2pStatus::class,
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
