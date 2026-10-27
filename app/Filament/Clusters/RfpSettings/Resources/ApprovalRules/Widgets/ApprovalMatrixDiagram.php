<?php

namespace App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Widgets;

use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\ApprovalRuleStep;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The active approval matrix as a picture: per request type (and Direct to VP), each
 * amount tier with its approver chain and who holds each role in a chosen company.
 * An optional amount highlights the tier it falls in.
 */
class ApprovalMatrixDiagram extends Widget
{
    protected string $view = 'filament.clusters.rfp-settings.widgets.approval-matrix-diagram';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public ?int $previewCompanyId = null;

    public string $previewAmount = '';

    public function mount(): void
    {
        $this->previewCompanyId = Company::query()->orderBy('code')->value('id');
    }

    /**
     * Active tiers grouped by request type and Direct to VP, lowest cap first.
     *
     * @return list<array{label: string, tiers: list<array{id: int, from: ?string, to: ?string, matches: bool, sequences: list<list<array{role: string, people: list<string>}>>}>}>
     */
    public function groups(): array
    {
        $people = $this->peopleByRole();
        $amount = is_numeric($this->previewAmount) ? $this->previewAmount : null;

        return ApprovalRule::query()
            ->where('is_active', true)
            ->with('steps.role')
            ->orderBy('is_direct_to_vp')
            ->orderBy('request_type')
            ->orderByRaw('max_amount is null')
            ->orderBy('max_amount')
            ->get()
            ->groupBy(fn (ApprovalRule $rule): string => ($rule->is_direct_to_vp ? 'vp' : 'std').'|'.($rule->request_type?->value ?? 'any'))
            ->map(function (Collection $rules) use ($people, $amount): array {
                $first = $rules->first();
                $type = $first->request_type?->getLabel();
                $matched = $amount === null ? null : $rules->first(fn (ApprovalRule $rule): bool => $rule->max_amount === null || bccomp($amount, (string) $rule->max_amount, 2) <= 0);
                $previousCap = null;

                return [
                    'label' => $first->is_direct_to_vp ? 'Direct to VP'.($type ? " · {$type}" : ' · any request type') : ($type ?? 'Any request type'),
                    'tiers' => $rules->map(function (ApprovalRule $rule) use (&$previousCap, $people, $matched): array {
                        $tier = [
                            'id' => $rule->id,
                            'from' => $previousCap === null ? null : bcadd((string) $previousCap, '0.01', 2),
                            'to' => $rule->max_amount === null ? null : (string) $rule->max_amount,
                            'matches' => $matched?->is($rule) ?? false,
                            'sequences' => $rule->steps
                                ->groupBy('step_order')
                                ->map(fn (Collection $steps): array => $steps->map(fn (ApprovalRuleStep $step): array => [
                                    'role' => $step->role->name,
                                    'people' => $people->get($step->role->name, []),
                                ])->values()->all())
                                ->values()
                                ->all(),
                        ];

                        $previousCap = $rule->max_amount;

                        return $tier;
                    })->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function companies(): array
    {
        return Company::query()->orderBy('code')->get()
            ->mapWithKeys(fn (Company $company): array => [$company->id => "{$company->code} - {$company->name}"])
            ->all();
    }

    /**
     * Active people per role name in the preview company.
     *
     * @return Collection<string, list<string>>
     */
    protected function peopleByRole(): Collection
    {
        return CompanyRole::query()
            ->where('company_id', $this->previewCompanyId)
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->with('user:id,name')
            ->get()
            ->groupBy(fn (CompanyRole $assignment): string => $assignment->role->value)
            ->map(fn (Collection $assignments): array => $assignments->pluck('user.name')->sort()->values()->all());
    }
}
