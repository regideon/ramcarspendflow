<?php

namespace App\Models\P2p;

use App\Enums\P2pStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of every P2P status transition and escalation.
 */
#[Table('p2p_approval_histories')]
#[Fillable([
    'p2p_invoice_id', 'actioned_by', 'from_status', 'to_status', 'remarks',
    'is_visible_to_vendor', 'is_visible_to_admin', 'escalation_reason', 'escalated_items', 'action_label',
])]
class ApprovalHistory extends Model
{
    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'p2p_invoice_id');
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
            'from_status' => P2pStatus::class,
            'to_status' => P2pStatus::class,
            'is_visible_to_vendor' => 'boolean',
            'is_visible_to_admin' => 'boolean',
            'escalated_items' => 'array',
        ];
    }
}
