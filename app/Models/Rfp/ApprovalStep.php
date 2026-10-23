<?php

namespace App\Models\Rfp;

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\User;
use Database\Factories\Rfp\ApprovalStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of an RFP's own approval route (a copy of a workflow stage or matrix role).
 */
#[Table('rfp_approval_steps')]
#[Fillable([
    'rfp_id', 'round', 'sequence', 'stage_position', 'kind', 'role', 'status', 'label',
    'can_return_to_requestor', 'return_to_position', 'can_reject', 'can_cancel',
    'state', 'acted_by', 'acted_at', 'remarks',
])]
class ApprovalStep extends Model
{
    /** @use HasFactory<ApprovalStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Rfp, $this>
     */
    public function rfp(): BelongsTo
    {
        return $this->belongsTo(Rfp::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /**
     * Close the step with the given outcome.
     */
    public function close(RfpApprovalStepState $state, ?User $by = null, ?string $remarks = null): void
    {
        $this->update([
            'state' => $state,
            'acted_by' => $by?->id,
            'acted_at' => $by ? now() : null,
            'remarks' => $remarks,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'sequence' => 'integer',
            'stage_position' => 'integer',
            'kind' => RfpStageKind::class,
            'role' => RoleName::class,
            'status' => RfpStatus::class,
            'can_return_to_requestor' => 'boolean',
            'return_to_position' => 'integer',
            'can_reject' => 'boolean',
            'can_cancel' => 'boolean',
            'state' => RfpApprovalStepState::class,
            'acted_at' => 'datetime',
        ];
    }
}
