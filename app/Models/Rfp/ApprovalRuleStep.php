<?php

namespace App\Models\Rfp;

use Database\Factories\Rfp\ApprovalRuleStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * An approver role within a matrix tier. Steps sharing a step_order are parallel.
 */
#[Table('rfp_approval_rule_steps')]
#[Fillable(['rfp_approval_rule_id', 'step_order', 'role_id'])]
class ApprovalRuleStep extends Model
{
    /** @use HasFactory<ApprovalRuleStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ApprovalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rfp_approval_rule_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
        ];
    }
}
