<?php

namespace App\Models\P2p;

use App\Enums\P2pStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hop of an escalation route. `role` is a Spatie role name, `origin`, or `vendor`.
 * A step with a custom `action` replaces the generic "Advance" button.
 */
#[Table('p2p_escalation_reason_steps')]
#[Fillable(['p2p_escalation_reason_id', 'step_order', 'role', 'status_id', 'action_label', 'action', 'override_header_status'])]
class EscalationReasonStep extends Model
{
    /** Resolve the hop role to whoever raised the escalation. */
    public const string ROLE_ORIGIN = 'origin';

    /** Warehouse hop action: reverse and re-GR the receipt in SAP. */
    public const string ACTION_REVERSE_REGR = 'reverse_and_regr';

    /**
     * @return BelongsTo<EscalationReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(EscalationReason::class, 'p2p_escalation_reason_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'status_id' => P2pStatus::class,
            'override_header_status' => 'boolean',
        ];
    }
}
