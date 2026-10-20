<?php

namespace App\Services\Rfp;

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\History;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The urgency chips above RFP Approvals and My Requests, per role. Each chip is "what to work on
 * first"; totals and trends live on the role dashboards instead.
 */
class RfpQueueChips
{
    public const int WAITING_DAYS = 3;

    public const int STUCK_DAYS = 5;

    /**
     * RFP Approvals: Admin sees the whole pipeline; AP roles see only what waits for them ("For my action").
     *
     * @return list<QueueChip>
     */
    public static function forApprovals(User $user): array
    {
        if ($user->hasRole(RoleName::Admin)) {
            return [
                static::overdue('all'),
                static::dueToday('all'),
                static::urgent('all'),
                new QueueChip('stuck', 'Stuck '.self::STUCK_DAYS.'+ days', Heroicon::OutlinedPauseCircle, 'gray', 'all',
                    fn (Builder $query): Builder => static::open(static::waitingSince($query, self::STUCK_DAYS))),
            ];
        }

        return array_values(array_filter([
            static::overdue('mine'),
            static::dueToday('mine'),
            static::urgent('mine'),
            $user->hasRole(RoleName::ApProcessor)
                ? new QueueChip('back_on_desk', 'Back on my desk', Heroicon::OutlinedArrowUturnLeft, 'info', 'mine', static::backOnDesk(...))
                : null,
            $user->hasAnyRole([RoleName::ApSupervisor, RoleName::ApManager])
                ? new QueueChip('waiting', 'Waiting '.self::WAITING_DAYS.'+ days', Heroicon::OutlinedClock, 'gray', 'mine',
                    fn (Builder $query): Builder => static::waitingSince($query, self::WAITING_DAYS))
                : null,
            $user->hasRole(RoleName::ApManager)
                ? new QueueChip('foreign', 'Foreign currency', Heroicon::OutlinedGlobeAlt, 'gray', 'mine',
                    fn (Builder $query): Builder => $query->whereHas('items.currency', fn (Builder $query) => $query->where('code', '!=', 'PHP')))
                : null,
        ]));
    }

    /**
     * My Requests: what the requestor should do, and what is on its way.
     *
     * @return list<QueueChip>
     */
    public static function forRequests(): array
    {
        return [
            new QueueChip('returned', 'Returned to me', Heroicon::OutlinedArrowUturnLeft, 'warning', 'for_revision'),
            new QueueChip('drafts', 'Drafts not submitted', Heroicon::OutlinedPencilSquare, 'gray', 'drafts'),
            new QueueChip('in_progress', 'In progress', Heroicon::OutlinedArrowPath, 'info', 'in_progress', showsAmount: true),
            new QueueChip('paid_this_month', 'Paid this month', Heroicon::OutlinedCheckBadge, 'success', 'closed',
                fn (Builder $query): Builder => $query
                    ->where('status', RfpStatus::Completed)
                    ->whereHas('histories', fn (Builder $query) => $query
                        ->where('to_status', RfpStatus::Completed)
                        ->where('created_at', '>=', now()->startOfMonth())),
                showsAmount: true),
        ];
    }

    protected static function overdue(string $tab): QueueChip
    {
        return new QueueChip('overdue', 'Overdue', Heroicon::OutlinedExclamationTriangle, 'danger', $tab,
            fn (Builder $query): Builder => static::open($query)->whereDate('due_date', '<', today()));
    }

    protected static function dueToday(string $tab): QueueChip
    {
        return new QueueChip('due_today', 'Due today', Heroicon::OutlinedCalendar, 'warning', $tab,
            fn (Builder $query): Builder => static::open($query)->whereDate('due_date', today()));
    }

    protected static function urgent(string $tab): QueueChip
    {
        return new QueueChip('urgent', 'Urgent', Heroicon::OutlinedFire, 'danger', $tab,
            fn (Builder $query): Builder => static::open($query)->whereHas('dueType', fn (Builder $query) => $query->where('code', 'urgent')));
    }

    /**
     * Still with an approver or Accounting (not a draft, not with the requestor, not finished).
     */
    protected static function open(Builder $query): Builder
    {
        return $query->whereNotIn('status', [RfpStatus::Draft, RfpStatus::ForRevision, ...RfpStatus::final()]);
    }

    /**
     * No status change for at least `$days` days.
     */
    protected static function waitingSince(Builder $query, int $days): Builder
    {
        return $query->where(
            History::query()->selectRaw('max(created_at)')->whereColumn('rfp_histories.rfp_id', 'rfps.id')->whereNotNull('to_status'),
            '<=',
            now()->subDays($days),
        );
    }

    /**
     * The last status change brought it back from a later step (AP Supervisor / Manager) or from the requestor.
     */
    protected static function backOnDesk(Builder $query): Builder
    {
        return $query->whereHas('histories', fn (Builder $query) => $query
            ->whereIn('from_status', [RfpStatus::ForAccountingApproval, RfpStatus::ForRevision])
            ->whereRaw('rfp_histories.id = (select max(h.id) from rfp_histories h where h.rfp_id = rfp_histories.rfp_id and h.to_status is not null)'));
    }
}
