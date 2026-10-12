<?php

namespace App\Models\P2p;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable escalation reason and the ordered hops it routes through.
 * Origin role: `vendor`, `processor`, or `any` (ORIGIN_ANY).
 */
#[Table('p2p_escalation_reasons')]
#[Fillable(['code', 'name', 'origin_role', 'is_active', 'sort'])]
class EscalationReason extends Model
{
    public const string ORIGIN_ANY = 'any';

    /**
     * @return HasMany<EscalationReasonStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(EscalationReasonStep::class, 'p2p_escalation_reason_id')->orderBy('step_order');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /**
     * Reasons a given role may raise (its own origin, plus "any").
     */
    #[Scope]
    protected function forRole(Builder $query, string $role): void
    {
        $query->whereIn('origin_role', [$role, self::ORIGIN_ANY]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
