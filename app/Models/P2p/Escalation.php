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
