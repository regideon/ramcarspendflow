<?php

namespace App\Models\P2p;

use App\Enums\P2pStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        $this->loadMissing('reason.steps');

        return $this->reason?->steps->firstWhere('step_order', $this->current_step_order);
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
