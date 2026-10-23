<?php

namespace App\Models\Rfp;

use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use Database\Factories\Rfp\WorkflowStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One stage of the global RFP approval workflow, edited under RFP Settings → Approval Workflow.
 */
#[Table('rfp_workflow_stages')]
#[Fillable([
    'position', 'kind', 'role', 'status', 'label',
    'can_return_to_requestor', 'return_to_position', 'can_reject', 'can_cancel',
])]
class WorkflowStage extends Model
{
    /** @use HasFactory<WorkflowStageFactory> */
    use HasFactory;

    /**
     * The workflow in order.
     *
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::query()->orderBy('position')->get();
    }

    /**
     * Replace the workflow, after checking it keeps the controls the business requires.
     *
     * @param  list<array<string, mixed>>  $stages  in order, without position
     *
     * @throws ValidationException
     */
    public static function replaceWith(array $stages): void
    {
        $stages = array_map(function (array $stage): array {
            $kind = $stage['kind'] instanceof RfpStageKind ? $stage['kind'] : RfpStageKind::from($stage['kind']);

            return [
                ...$stage,
                'kind' => $kind,
                'role' => $kind->hasRole() ? ($stage['role'] ?? null) : null,
                'return_to_position' => filled($stage['return_to_position'] ?? null) ? (int) $stage['return_to_position'] : null,
            ];
        }, array_values($stages));

        $problems = static::problemsWith($stages);

        if ($problems !== []) {
            throw ValidationException::withMessages(['stages' => $problems]);
        }

        DB::transaction(function () use ($stages): void {
            static::query()->delete();

            foreach ($stages as $index => $stage) {
                static::create([...$stage, 'position' => $index + 1]);
            }
        });
    }

    /**
     * What is wrong with a workflow, if anything: Treasury pays last, AP reviews first and
     * processes once, at most one matrix, and returns only go to an earlier stage.
     *
     * @param  list<array{kind: RfpStageKind, role: mixed, return_to_position: ?int}>  $stages  in order
     * @return list<string>
     */
    public static function problemsWith(array $stages): array
    {
        $kinds = array_column($stages, 'kind');
        $count = fn (RfpStageKind $kind): int => count(array_filter($kinds, fn (RfpStageKind $each): bool => $each === $kind));
        $apRoles = [RoleName::ApProcessor->value, RoleName::ApSupervisor->value, RoleName::ApManager->value];
        $roleOf = fn (array $stage): ?string => $stage['role'] instanceof RoleName ? $stage['role']->value : $stage['role'];

        $problems = [];

        if ($stages === []) {
            return ['Add at least one stage.'];
        }

        if ($count(RfpStageKind::Payment) !== 1 || end($kinds) !== RfpStageKind::Payment) {
            $problems[] = 'The workflow must end with exactly one payment release stage.';
        }

        if ($count(RfpStageKind::ApProcessing) !== 1) {
            $problems[] = 'The workflow must have exactly one AP processing stage.';
        }

        if ($count(RfpStageKind::Matrix) > 1) {
            $problems[] = 'The workflow can have only one approval matrix stage.';
        }

        if ($stages[0]['kind'] !== RfpStageKind::Approval || ! in_array($roleOf($stages[0]), $apRoles, true)) {
            $problems[] = 'The first stage must be an AP review (AP Processor, AP Supervisor or AP Manager).';
        }

        foreach ($stages as $index => $stage) {
            $position = $index + 1;

            if ($stage['kind']->hasRole() && blank($roleOf($stage))) {
                $problems[] = "Stage {$position} needs a role.";
            }

            $target = $stage['return_to_position'];

            if ($target !== null && ($target < 1 || $target >= $position || $stages[$target - 1]['kind'] === RfpStageKind::Matrix)) {
                $problems[] = "Stage {$position} can only return to an earlier stage that is not the approval matrix.";
            }
        }

        return $problems;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'kind' => RfpStageKind::class,
            'role' => RoleName::class,
            'status' => RfpStatus::class,
            'can_return_to_requestor' => 'boolean',
            'return_to_position' => 'integer',
            'can_reject' => 'boolean',
            'can_cancel' => 'boolean',
        ];
    }
}
