<?php

namespace App\Models\Rfp;

use App\Enums\RfpRequestType;
use Database\Factories\Rfp\ApprovalRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One tier of the Step 3 approval matrix. A tier covers PHP amounts above the next
 * lower tier's max_amount up to and including its own; a null max_amount has no limit.
 */
#[Table('rfp_approval_rules')]
#[Fillable(['request_type', 'is_direct_to_vp', 'max_amount', 'description', 'is_active'])]
class ApprovalRule extends Model
{
    /** @use HasFactory<ApprovalRuleFactory> */
    use HasFactory;

    /**
     * @return HasMany<ApprovalRuleStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRuleStep::class, 'rfp_approval_rule_id')->orderBy('step_order');
    }

    /**
     * Find the tier that applies to a request. A rule for the specific request type wins
     * over a rule for any type (request_type = null); among those, the lowest cap wins.
     */
    public static function findFor(RfpRequestType $requestType, string|float $amountInPhp, bool $isDirectToVp = false): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_direct_to_vp', $isDirectToVp)
            ->where(fn ($query) => $query->where('request_type', $requestType)->orWhereNull('request_type'))
            ->where(fn ($query) => $query->where('max_amount', '>=', $amountInPhp)->orWhereNull('max_amount'))
            ->orderByRaw('request_type is null')
            ->orderByRaw('max_amount is null')
            ->orderBy('max_amount')
            ->with('steps.role')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => RfpRequestType::class,
            'is_direct_to_vp' => 'boolean',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
