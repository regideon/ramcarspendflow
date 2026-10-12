<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\EscalationReasonStep;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Services\P2p\Escalation\StepActionContext;
use App\Services\P2p\Escalation\StepActionRegistry;
use App\Services\P2p\Mail\EscalationMailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starts, advances and resolves P2P escalations along the hops configured in
 * `p2p_escalation_reasons` + `p2p_escalation_reason_steps` (data-driven routes).
 *
 * One escalation row is created per GR, all sharing a batch number. The header
 * (invoice) status follows the hops: a single hop status, or `PartiallyEscalated`
 * when a multi-GR request is split across hops. See docs/p2p/06-workflow-statuses.md.
 */
class P2pEscalationRoutingService
{
    public function __construct(
        private readonly P2pGoodReceiptStatusService $goodReceiptStatus,
        private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus,
        private readonly StepActionRegistry $actions,
        private readonly EscalationMailService $mail,
    ) {}

    /**
     * Raise an escalation for one or more GRs of an invoice.
     *
     * @param  array<int, int>  $grStateIds
     * @return Collection<int, Escalation>
     */
    public function start(Invoice $invoice, string $reasonCode, array $grStateIds, User $by, string $remarks): Collection
    {
        if (trim($remarks) === '') {
            throw ValidationException::withMessages(['remarks' => 'Remarks are required.']);
        }

        return DB::transaction(function () use ($invoice, $reasonCode, $grStateIds, $by, $remarks): Collection {
            $reason = EscalationReason::query()->where('code', $reasonCode)->where('is_active', true)->firstOrFail();
            $steps = $reason->steps;

            if ($steps->isEmpty()) {
                throw ValidationException::withMessages(['reason' => 'This escalation reason has no route.']);
            }

            $states = GoodReceiptState::query()->whereIn('id', $grStateIds)->get();

            if ($states->isEmpty()) {
                throw ValidationException::withMessages(['gr_numbers' => 'Select at least one goods receipt to escalate.']);
            }

            $first = $steps->first();
            $batch = 'BATCH-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

            $created = $states->map(fn (GoodReceiptState $state): Escalation => Escalation::query()->create([
                'escalation_number' => 'ESC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'batch_number' => $batch,
                'p2p_invoice_id' => $invoice->id,
                'p2p_good_receipt_state_id' => $state->id,
                'p2p_escalation_reason_id' => $reason->id,
                'current_step_order' => 1,
                'current_role' => $this->resolveRole($first, $reason->origin_role),
                'origin_role' => $reason->origin_role,
                'status' => 'open',
                'from_status' => $state->status,
                'remarks' => $remarks,
                'actioned_by' => $by->id,
                'escalated_at' => now(),
            ]));

            $states->each(fn (GoodReceiptState $state) => $this->goodReceiptStatus->recalculate($state));
            $this->syncHeaderStatus($invoice->fresh());

            ApprovalHistory::query()->create([
                'p2p_invoice_id' => $invoice->id,
                'actioned_by' => $by->id,
                'from_status' => $invoice->status,
                'to_status' => $invoice->fresh()->status,
                'remarks' => $remarks,
                'escalation_reason' => $reason->name,
                'escalated_items' => $states->pluck('good_receipt_number')->all(),
                'is_visible_to_vendor' => true,
                'is_visible_to_admin' => true,
            ]);

            $created->each(function (Escalation $escalation): void {
                $this->mail->sendForCurrentHop($escalation);
                $this->mail->sendOriginNotice($escalation);
            });

            return $created;
        });
    }

    /**
     * Run the current hop's action (if any) and move to the next hop, or resolve at
     * the terminal hop.
     */
    public function advance(Escalation $escalation, ?User $by, ?string $remarks = null): void
    {
        DB::transaction(function () use ($escalation, $by, $remarks): void {
            /** @var Escalation $escalation */
            $escalation = Escalation::query()->whereKey($escalation->getKey())->lockForUpdate()->firstOrFail();
            $steps = $escalation->reason->steps;
            $current = $steps->firstWhere('step_order', $escalation->current_step_order);

            // A custom hop action (e.g. "Reverse & re-GR") replaces the generic
            // Advance: it may settle the hop without walking the route, or decline
            // to act at all (leaving the escalation where it is).
            $action = $current?->action !== null ? $this->actions->resolve($current->action) : null;

            if ($action !== null) {
                $completion = $action->handle(new StepActionContext($escalation, $by, $remarks));

                if (! $completion->handled) {
                    return;
                }

                if ($completion->resolved) {
                    $this->resolve($escalation, $by, $remarks);

                    return;
                }
            }

            $next = $steps->firstWhere('step_order', $escalation->current_step_order + 1);

            if ($next === null) {
                $this->resolve($escalation, $by, $remarks);

                return;
            }

            // The label that pushed the request forward: the custom action (or the
            // hop's configured label), falling back to the generic "Advance".
            $actionLabel = $action !== null
                ? $current->action_label
                : ($current->action_label ?? 'Advance');

            $escalation->forceFill([
                'current_step_order' => $next->step_order,
                'current_role' => $this->resolveRole($next, $escalation->origin_role),
            ])->save();

            $this->goodReceiptStatus->recalculate($escalation->goodReceiptState);

            // A terminal hop hands the request back to the originator, so entering
            // it ends the cycle rather than leaving it open on that role.
            if ($next->override_header_status) {
                $this->afterClosed($escalation, $by, $remarks, $actionLabel);

                $this->mail->sendForCurrentHop($escalation->fresh());

                return;
            }

            $this->syncHeaderStatus($escalation->invoice->fresh());

            $this->mail->sendForCurrentHop($escalation->fresh());

            $this->history($escalation, $by, $remarks, $actionLabel ?? 'Escalation advanced.');
        });
    }

    /**
     * Resolve an escalation; the GR returns to the status it had before escalating.
     */
    public function resolve(Escalation $escalation, ?User $by, ?string $remarks = null): void
    {
        DB::transaction(function () use ($escalation, $by, $remarks): void {
            /** @var Escalation $escalation */
            $escalation = Escalation::query()->whereKey($escalation->getKey())->lockForUpdate()->firstOrFail();

            $escalation->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'actioned_by' => $by->id,
            ])->save();

            $this->afterClosed($escalation, $by, $remarks, 'Escalation resolved.');
        });
    }

    public function cancel(Escalation $escalation, ?User $by, ?string $remarks = null): void
    {
        DB::transaction(function () use ($escalation, $by, $remarks): void {
            /** @var Escalation $escalation */
            $escalation = Escalation::query()->whereKey($escalation->getKey())->lockForUpdate()->firstOrFail();

            $escalation->forceFill([
                'status' => 'cancelled',
                'resolved_at' => now(),
                'actioned_by' => $by->id,
            ])->save();

            $this->afterClosed($escalation, $by, $remarks, 'Escalation cancelled.');
        });
    }

    /**
     * Recompute the header (invoice) status from its GRs' open escalations: a single
     * hop status, or `PartiallyEscalated` when they diverge; when nothing is open,
     * restore the pre-escalation status.
     */
    protected function syncHeaderStatus(Invoice $invoice): void
    {
        $open = Escalation::query()
            ->where('p2p_invoice_id', $invoice->id)
            ->where('status', 'open')
            ->with('reason.steps')
            ->get();

        if ($open->isNotEmpty()) {
            $statuses = $open->map(fn (Escalation $e): ?P2pStatus => $this->hopStatus($e))->filter()->unique(fn (P2pStatus $s): int => $s->value);
            $status = $statuses->count() === 1 ? $statuses->first() : P2pStatus::PartiallyEscalated;
        } else {
            $last = Escalation::query()
                ->where('p2p_invoice_id', $invoice->id)
                ->whereIn('status', ['resolved', 'cancelled'])
                ->latest('updated_at')
                ->first();
            $status = $last?->from_status ?? $invoice->status;
        }

        if ($invoice->status !== $status) {
            $invoice->forceFill(['status' => $status, 'escalated_at' => $open->isNotEmpty() ? now() : $invoice->escalated_at])->save();
        }
    }

    protected function hopStatus(Escalation $escalation): ?P2pStatus
    {
        $step = $escalation->reason?->steps->firstWhere('step_order', $escalation->current_step_order);

        return $step?->status_id ?? $escalation->from_status;
    }

    protected function resolveRole(EscalationReasonStep $step, ?string $originRole): string
    {
        return $step->role === EscalationReasonStep::ROLE_ORIGIN ? ($originRole ?? 'origin') : $step->role;
    }

    protected function afterClosed(Escalation $escalation, ?User $by, ?string $remarks, string $default): void
    {
        // Restore the header first: the GR's derived status follows the invoice.
        $this->syncHeaderStatus($escalation->invoice->fresh());

        if ($escalation->goodReceiptState !== null) {
            $this->goodReceiptStatus->recalculate($escalation->goodReceiptState);
        }

        SapPurchaseOrder::query()
            ->whereIn('purchase_order', $escalation->invoice->po_numbers ?? [])
            ->get()
            ->each(fn (SapPurchaseOrder $po) => $this->purchaseOrderStatus->recalculate($po));

        $this->history($escalation, $by, $remarks, $default);
    }

    protected function history(Escalation $escalation, ?User $by, ?string $remarks, string $default): void
    {
        ApprovalHistory::query()->create([
            'p2p_invoice_id' => $escalation->p2p_invoice_id,
            'actioned_by' => $by?->id,
            'from_status' => null,
            'to_status' => $escalation->invoice->status,
            'remarks' => $remarks ?: $default,
            'is_visible_to_vendor' => true,
            'is_visible_to_admin' => true,
        ]);
    }
}
