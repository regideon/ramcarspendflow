<?php

namespace App\Services\Rfp;

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpRequestType;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\ApprovalRuleStep;
use App\Models\Rfp\ApprovalStep;
use App\Models\Rfp\Rfp;
use App\Models\Rfp\WorkflowStage;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an RFP through its approval route. On submit the global workflow is copied onto
 * the RFP (the Matrix stage expanded into the matching tier's roles), so later workflow
 * or matrix edits never change RFPs in flight. Steps sharing a sequence run in parallel.
 * See docs/12-rfp-dynamic-workflow.md.
 */
class ApprovalEngine
{
    /**
     * Route a newly submitted RFP. Call inside a transaction on a locked RFP.
     *
     * @throws ValidationException
     */
    public function start(Rfp $rfp, User $by): void
    {
        $this->openRound($rfp, $by, isResubmission: false);
    }

    /**
     * Route an RFP the requestor revised. Stages before the matrix that already approved
     * are not repeated, and the matrix is skipped when it approved this same amount.
     * Call inside a transaction on a locked RFP.
     *
     * @throws ValidationException
     */
    public function resubmit(Rfp $rfp, User $by): void
    {
        $this->openRound($rfp, $by, isResubmission: true);
    }

    /**
     * Approve the user's step. $verb names it in the history ("Endorsed" at AP review).
     *
     * @throws AuthorizationException
     */
    public function approve(Rfp $rfp, User $by, ?string $remarks = null, string $verb = 'Approved'): void
    {
        $this->locked($rfp, function (Rfp $rfp) use ($by, $remarks, $verb): void {
            $step = $this->stepFor($rfp, $by);
            $step->close(RfpApprovalStepState::Approved, $by, $remarks);

            $stillWaiting = $this->openSteps($rfp)->firstWhere('state', RfpApprovalStepState::Active);

            if ($stillWaiting) {
                $rfp->current_approver_role = $stillWaiting->role;
                $rfp->transitionTo($rfp->status, $by, $remarks, "{$verb} ({$step->label})");

                return;
            }

            $this->advance($rfp, $by, "{$verb} ({$step->label})", $remarks);
        });
    }

    /**
     * Send the RFP back to the requestor for revision. With keepMatrixApproval, a
     * resubmission with the same amount skips the matrix.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function returnToRequestor(Rfp $rfp, User $by, string $remarks, bool $keepMatrixApproval = false): void
    {
        $this->locked($rfp, function (Rfp $rfp) use ($by, $remarks, $keepMatrixApproval): void {
            $step = $this->stepFor($rfp, $by, fn (ApprovalStep $step): bool => $step->can_return_to_requestor);
            $this->requireRemarks($remarks);

            $this->closeRoute($rfp, $step, RfpApprovalStepState::Returned, $by, $remarks);

            if (! $keepMatrixApproval) {
                $rfp->routed_php_amount = null;
            }

            $rfp->transitionTo(RfpStatus::ForRevision, $by, $remarks, 'Returned to requestor');

            $this->notifyRequestor($rfp, "{$rfp->rfp_number} was returned for revision", "{$by->name}: {$remarks}");
        });
    }

    /**
     * Send the RFP back to an earlier stage (e.g. AP Supervisor → AP Processing). That
     * stage and every stage after it run again.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function returnToStage(Rfp $rfp, User $by, string $remarks): void
    {
        $this->locked($rfp, function (Rfp $rfp) use ($by, $remarks): void {
            $step = $this->stepFor($rfp, $by, fn (ApprovalStep $step): bool => $step->return_to_position !== null);
            $this->requireRemarks($remarks);

            $round = $this->roundSteps($rfp);
            $restartFrom = $round->where('stage_position', $step->return_to_position)->max('sequence');
            $rerun = $round->filter(fn (ApprovalStep $row): bool => $row->sequence >= $restartFrom
                && $row->state !== RfpApprovalStepState::Superseded);

            $this->closeRoute($rfp, $step, RfpApprovalStepState::Returned, $by, $remarks);

            $sequence = $round->max('sequence');

            foreach ($rerun->groupBy('sequence') as $parallel) {
                $sequence++;

                foreach ($parallel as $row) {
                    $rfp->approvalSteps()->create([
                        ...$row->only([
                            'round', 'stage_position', 'kind', 'role', 'status', 'label',
                            'can_return_to_requestor', 'return_to_position', 'can_reject', 'can_cancel',
                        ]),
                        'sequence' => $sequence,
                        'state' => $row->state === RfpApprovalStepState::Skipped ? RfpApprovalStepState::Skipped : RfpApprovalStepState::Pending,
                    ]);
                }
            }

            $target = $rerun->firstWhere('stage_position', $step->return_to_position);
            $this->advance($rfp, $by, "Returned to {$target?->label}", $remarks);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reject(Rfp $rfp, User $by, string $remarks): void
    {
        $this->locked($rfp, function (Rfp $rfp) use ($by, $remarks): void {
            $step = $this->stepFor($rfp, $by, fn (ApprovalStep $step): bool => $step->can_reject);
            $this->requireRemarks($remarks);

            $this->closeRoute($rfp, $step, RfpApprovalStepState::Rejected, $by, $remarks);
            $rfp->transitionTo(RfpStatus::Rejected, $by, $remarks, 'Rejected');

            $this->notifyRequestor($rfp, "{$rfp->rfp_number} was rejected", "{$by->name}: {$remarks}");
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cancel(Rfp $rfp, User $by, string $remarks): void
    {
        $this->locked($rfp, function (Rfp $rfp) use ($by, $remarks): void {
            $step = $this->stepFor($rfp, $by, fn (ApprovalStep $step): bool => $step->can_cancel);
            $this->requireRemarks($remarks);

            $this->closeRoute($rfp, $step, RfpApprovalStepState::Cancelled, $by, $remarks);
            $rfp->transitionTo(RfpStatus::Cancelled, $by, $remarks, 'Cancelled');

            $this->notifyRequestor($rfp, "{$rfp->rfp_number} was cancelled", "{$by->name}: {$remarks}");
        });
    }

    /**
     * Give an RFP that arrived mid-approval (a legacy import) its route, picked up where
     * it is: steps before its current status are marked as done before the import (the
     * imported history says by whom), the current one is active, the rest pending.
     * Writes no history and sends no notifications. Call inside a transaction.
     *
     * @return string|null why it could not be routed, or null when it was
     */
    public function adopt(Rfp $rfp): ?string
    {
        if (! $rfp->status?->isAwaitingApproval() || $rfp->approvalSteps()->exists()) {
            return 'not in progress, or already routed';
        }

        try {
            $round = $this->buildRound($rfp, isResubmission: false);
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first();
        }

        $rfp->approval_round = $round;
        $steps = $this->roundSteps($rfp);
        $atStatus = $steps->where('status', $rfp->status)->where('state', RfpApprovalStepState::Pending);
        $current = $atStatus->firstWhere('role', $rfp->current_approver_role) ?? $atStatus->first();

        if ($current === null) {
            $rfp->approvalSteps()->delete();

            return "the workflow has no stage for status {$rfp->status->getLabel()}";
        }

        foreach ($steps as $step) {
            match (true) {
                $step->sequence < $current->sequence && $step->state === RfpApprovalStepState::Pending => $step->update([
                    'state' => RfpApprovalStepState::Approved,
                    'remarks' => 'Done in the legacy RFP Portal before the import',
                ]),
                $step->sequence === $current->sequence => $step->update(['state' => RfpApprovalStepState::Active]),
                default => null,
            };
        }

        $rfp->routed_php_amount = $rfp->total_php_amount;
        $rfp->current_approver_role ??= $current->role;
        $rfp->saveQuietly();

        return null;
    }

    /**
     * The step the user may act on now, if any: an active step for a role they hold in
     * the RFP's company.
     */
    public function actionableStep(Rfp $rfp, User $user): ?ApprovalStep
    {
        if (! $rfp->status?->isAwaitingApproval()) {
            return null;
        }

        $roles = $user->rolesIn($rfp->company_id);

        // From the loaded route (refreshed after every engine action): a page asks once per action.
        return $rfp->approvalSteps->first(fn (ApprovalStep $step): bool => $step->round === $rfp->approval_round
            && $step->state === RfpApprovalStepState::Active
            && in_array($step->role?->value, $roles, true));
    }

    /**
     * The route the workflow would give a new request, for previews: one entry per
     * sequence, parallel roles joined with " + ".
     *
     * @return list<string>
     */
    public function preview(?RfpRequestType $requestType, string $amountInPhp, bool $isDirectToVp, bool $isPreApproved = false): array
    {
        return collect($this->routeFor(WorkflowStage::ordered(), $requestType, $amountInPhp, $isDirectToVp, $isPreApproved))
            ->flatMap(fn (array $node): array => $node['stage']->kind !== RfpStageKind::Matrix
                ? [$node['stage']->label]
                : ($node['note'] !== null
                    ? [$node['note']]
                    : array_map(fn (array $roles): string => implode(' + ', array_map(fn (RoleName $role): string => $role->value, $roles)), $node['sequences'])))
            ->values()
            ->all();
    }

    /**
     * The route a set of stages (saved or a draft) gives a request: per stage, its
     * sequences of roles (one, or the matrix tier's), or a note when the matrix is skipped
     * or has no tier.
     *
     * @param  iterable<WorkflowStage>  $stages  in order
     * @return list<array{stage: WorkflowStage, sequences: list<list<RoleName>>, note: ?string}>
     */
    public function routeFor(iterable $stages, ?RfpRequestType $requestType, string $amountInPhp, bool $isDirectToVp, bool $isPreApproved = false): array
    {
        $route = [];

        foreach ($stages as $stage) {
            if ($stage->kind !== RfpStageKind::Matrix) {
                $route[] = ['stage' => $stage, 'sequences' => $stage->role ? [[$stage->role]] : [], 'note' => null];

                continue;
            }

            $rule = ! $isPreApproved && $requestType ? ApprovalRule::findFor($requestType, $amountInPhp, $isDirectToVp) : null;

            $route[] = [
                'stage' => $stage,
                'sequences' => $rule === null ? [] : $rule->steps
                    ->groupBy('step_order')
                    ->map(fn (Collection $steps): array => $steps->map(fn (ApprovalRuleStep $step): RoleName => RoleName::from($step->role->name))->all())
                    ->values()
                    ->all(),
                'note' => match (true) {
                    $isPreApproved => 'Pre-approved (no matrix)',
                    $rule === null => '(no matching tier)',
                    default => null,
                },
            ];
        }

        return $route;
    }

    /**
     * @throws ValidationException
     */
    private function openRound(Rfp $rfp, User $by, bool $isResubmission): void
    {
        $rfp->approval_round = $this->buildRound($rfp, $isResubmission);
        $rfp->routed_php_amount = $rfp->total_php_amount;

        $this->advance($rfp, $by, $isResubmission ? 'Resubmitted' : 'Submitted');
    }

    /**
     * Write the next round's steps, all pending (or skipped), and return its number.
     *
     * @throws ValidationException
     */
    private function buildRound(Rfp $rfp, bool $isResubmission): int
    {
        $stages = WorkflowStage::ordered();

        if ($stages->isEmpty()) {
            throw ValidationException::withMessages(['workflow' => 'No approval workflow is set up. Ask an Admin to set one up under RFP Settings.']);
        }

        $previousRound = $isResubmission ? $this->roundSteps($rfp) : collect();
        $matrixPosition = $stages->firstWhere('kind', RfpStageKind::Matrix)?->position;
        $matrixStillApproved = $isResubmission
            && $rfp->routed_php_amount !== null
            && bccomp((string) $rfp->routed_php_amount, (string) $rfp->total_php_amount, 2) === 0
            && $previousRound->where('kind', RfpStageKind::Matrix)->isNotEmpty()
            && $previousRound->where('kind', RfpStageKind::Matrix)
                ->every(fn (ApprovalStep $row): bool => in_array($row->state, [RfpApprovalStepState::Approved, RfpApprovalStepState::Skipped], true));

        $round = $rfp->approval_round + 1;
        $sequence = 0;

        foreach ($stages as $stage) {
            if ($stage->kind === RfpStageKind::Matrix) {
                $skipReason = match (true) {
                    $matrixStillApproved => 'Matrix approved in an earlier round',
                    ! $isResubmission && $rfp->is_pre_approved => 'Pre-approved (no matrix)',
                    default => null,
                };

                if ($skipReason !== null) {
                    $this->addStep($rfp, $stage, $round, ++$sequence, RfpApprovalStepState::Skipped, label: $skipReason);

                    continue;
                }

                foreach ($this->matrixRoles($rfp) as $parallelRoles) {
                    $sequence++;

                    foreach ($parallelRoles as $role) {
                        $this->addStep($rfp, $stage, $round, $sequence, RfpApprovalStepState::Pending, $role, $role->value);
                    }
                }

                continue;
            }

            $alreadyApproved = $matrixPosition !== null
                && $stage->position < $matrixPosition
                && $previousRound->where('stage_position', $stage->position)->where('state', RfpApprovalStepState::Approved)->isNotEmpty();

            $this->addStep($rfp, $stage, $round, ++$sequence, $alreadyApproved ? RfpApprovalStepState::Skipped : RfpApprovalStepState::Pending);
        }

        return $round;
    }

    /**
     * The matrix tier's roles for the RFP, one entry per step order.
     *
     * @return Collection<int, Collection<int, RoleName>>
     *
     * @throws ValidationException
     */
    private function matrixRoles(Rfp $rfp): Collection
    {
        $rule = $rfp->request_type
            ? ApprovalRule::findFor($rfp->request_type, $rfp->total_php_amount, $rfp->is_direct_to_vp)
            : null;

        if ($rule === null || $rule->steps->isEmpty()) {
            throw ValidationException::withMessages(['workflow' => 'No approval matrix tier matches this request. Ask an Admin to check the Approval Matrix.']);
        }

        return $rule->steps
            ->groupBy('step_order')
            ->map(fn (Collection $steps): Collection => $steps->map(fn (ApprovalRuleStep $step): RoleName => RoleName::from($step->role->name)))
            ->values();
    }

    private function addStep(Rfp $rfp, WorkflowStage $stage, int $round, int $sequence, RfpApprovalStepState $state, ?RoleName $role = null, ?string $label = null): void
    {
        $rfp->approvalSteps()->create([
            'round' => $round,
            'sequence' => $sequence,
            'stage_position' => $stage->position,
            'kind' => $stage->kind,
            'role' => $role ?? $stage->role,
            'status' => $stage->status,
            'label' => $label ?? $stage->label,
            'can_return_to_requestor' => $stage->can_return_to_requestor,
            'return_to_position' => $stage->return_to_position,
            'can_reject' => $stage->can_reject,
            'can_cancel' => $stage->can_cancel,
            'state' => $state,
        ]);
    }

    /**
     * Activate the next pending sequence of the round, or complete the RFP when none is left.
     */
    private function advance(Rfp $rfp, User $by, string $action, ?string $remarks = null): void
    {
        $autoApproved = collect();

        do {
            $pending = $this->openSteps($rfp)->where('state', RfpApprovalStepState::Pending);
            $next = $pending->where('sequence', $pending->min('sequence'));
            $isParkedMatrix = $next->isNotEmpty() && $next->first()->kind === RfpStageKind::Matrix && config('rfp.auto_approve_matrix');

            if ($isParkedMatrix) {
                $next->each(fn (ApprovalStep $step) => $step->update([
                    'state' => RfpApprovalStepState::Approved,
                    'acted_at' => now(),
                    'remarks' => 'Auto-approved: matrix approval is not live yet',
                ]));
                $autoApproved->push($next->pluck('label')->implode(' + '));
            }
        } while ($isParkedMatrix);

        $rfp->current_approver_id = null;

        if ($next->isEmpty()) {
            $rfp->current_approver_role = null;
            $rfp->transitionTo(RfpStatus::Completed, $by, $remarks, $action);
            $this->recordAutoApproval($rfp, $autoApproved);
            $this->notifyRequestor($rfp, "{$rfp->rfp_number} is completed", "{$action} by {$by->name}.");

            return;
        }

        $next->each(fn (ApprovalStep $step) => $step->update(['state' => RfpApprovalStepState::Active]));

        $rfp->current_approver_role = $next->first()->role;
        $rfp->transitionTo($next->first()->status, $by, $remarks, $action);
        $this->recordAutoApproval($rfp, $autoApproved);
        $this->notifyApprovers($rfp, $next);
    }

    /**
     * @param  Collection<int, string>  $sequences  matrix approvers passed automatically, in order
     */
    private function recordAutoApproval(Rfp $rfp, Collection $sequences): void
    {
        if ($sequences->isEmpty()) {
            return;
        }

        $rfp->histories()->create([
            'action' => 'Matrix auto-approved',
            'from_status' => $rfp->status,
            'to_status' => $rfp->status,
            'remarks' => $sequences->implode(' → ').' (matrix approval is not live yet)',
        ]);
    }

    /**
     * Tell everyone who can act on the newly active steps, once the change is committed.
     *
     * @param  Collection<int, ApprovalStep>  $steps
     */
    private function notifyApprovers(Rfp $rfp, Collection $steps): void
    {
        $roles = $steps->pluck('role')->filter()->map(fn (RoleName $role): string => $role->value)->unique()->all();

        DB::afterCommit(function () use ($rfp, $roles): void {
            User::query()
                ->where('is_active', true)
                ->whereHas('companyRoles', fn ($query) => $query->where('company_id', $rfp->company_id)->whereIn('role', $roles))
                ->get()
                ->each(fn (User $user) => Notification::make()
                    ->title("{$rfp->rfp_number} is waiting for you")
                    ->body("{$rfp->requestor?->name} · PHP ".number_format((float) $rfp->total_php_amount, 2))
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->actions([Action::make('open')->label('Open')->url($rfp->commentUrlFor($user))->markAsRead()])
                    ->sendToDatabase($user));
        });
    }

    private function notifyRequestor(Rfp $rfp, string $title, ?string $body = null): void
    {
        DB::afterCommit(function () use ($rfp, $title, $body): void {
            $requestor = $rfp->requestor;

            if ($requestor === null) {
                return;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon(Heroicon::OutlinedDocumentText)
                ->actions([Action::make('open')->label('Open')->url($rfp->commentUrlFor($requestor))->markAsRead()])
                ->sendToDatabase($requestor);
        });
    }

    /**
     * Close the acting step, and supersede every other step still open in the round.
     */
    private function closeRoute(Rfp $rfp, ApprovalStep $step, RfpApprovalStepState $outcome, User $by, string $remarks): void
    {
        $step->close($outcome, $by, $remarks);

        $rfp->approvalSteps()
            ->where('round', $rfp->approval_round)
            ->whereIn('state', RfpApprovalStepState::open())
            ->update(['state' => RfpApprovalStepState::Superseded]);

        $rfp->current_approver_role = null;
        $rfp->current_approver_id = null;
    }

    /**
     * The user's active step, optionally required to allow an action.
     *
     * @param  (Closure(ApprovalStep): bool)|null  $allows
     *
     * @throws AuthorizationException
     */
    private function stepFor(Rfp $rfp, User $user, ?Closure $allows = null): ApprovalStep
    {
        $step = $this->actionableStep($rfp, $user);

        if ($step === null) {
            throw new AuthorizationException("{$rfp->rfp_number} is not waiting for you.");
        }

        if ($allows && ! $allows($step)) {
            throw new AuthorizationException("{$step->label} cannot do that on {$rfp->rfp_number}.");
        }

        return $step;
    }

    /**
     * @return Collection<int, ApprovalStep>
     */
    private function roundSteps(Rfp $rfp): Collection
    {
        return $rfp->approvalSteps()->where('round', $rfp->approval_round)->orderBy('sequence')->orderBy('id')->get()->toBase();
    }

    /**
     * @return Collection<int, ApprovalStep>
     */
    private function openSteps(Rfp $rfp): Collection
    {
        return $this->roundSteps($rfp)->filter(fn (ApprovalStep $step): bool => in_array($step->state, RfpApprovalStepState::open(), true));
    }

    /**
     * @throws ValidationException
     */
    private function requireRemarks(string $remarks): void
    {
        if (blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Remarks are required.']);
        }
    }

    /**
     * Run an action on a locked copy of the RFP, then refresh the caller's instance.
     *
     * @param  Closure(Rfp): void  $callback
     */
    private function locked(Rfp $rfp, Closure $callback): void
    {
        DB::transaction(function () use ($rfp, $callback): void {
            $callback(Rfp::query()->lockForUpdate()->findOrFail($rfp->getKey()));
        });

        $rfp->reloadWithRelations();
    }
}
