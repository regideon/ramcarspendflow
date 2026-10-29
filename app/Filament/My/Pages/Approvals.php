<?php

namespace App\Filament\My\Pages;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Filament\My\Support\SampleRfps;
use App\Models\Rfp\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The matrix approver's queue: RFPs waiting on their sign-off, and the ones they
 * already approved, returned or rejected.
 *
 * Rows come from SampleRfps until the design is signed off; decisions happen on the
 * RFP detail page.
 */
class Approvals extends Page
{
    protected static string $layout = 'filament.my.layout';

    protected string $view = 'filament.my.pages.approvals';

    protected static ?string $slug = 'approvals';

    protected static ?string $title = 'Approvals';

    #[Url]
    public string $tab = 'waiting';

    #[Url]
    public string $sort = 'due';

    /** Reviewed by Me filter: all, approved, returned or rejected. */
    #[Url]
    public string $decision = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    /**
     * Filter sheet: due (For Approval), decided (Reviewed by Me), request types,
     * requestor departments and an amount range.
     *
     * @var array{due: string, decided: string, type: list<string>, department: list<string>, min: string, max: string}
     */
    #[Url]
    public array $filters = self::NO_FILTERS;

    public const array NO_FILTERS = ['due' => '', 'decided' => '', 'type' => [], 'department' => [], 'min' => '', 'max' => ''];

    /**
     * Numbers mass approved on this page. Preview only: nothing is saved yet, so they
     * just drop out of the list until the page is reloaded.
     *
     * @var list<string>
     */
    public array $approved = [];

    /** @var array<string, string> */
    public const array DUE_FILTERS = ['overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Due this week'];

    /** @var array<string, string> */
    public const array DECIDED_FILTERS = ['month' => 'This month', 'quarter' => 'Last 3 months'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isMatrixApprover();
    }

    /**
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return [
            'waiting' => 'For Approval',
            'done' => 'Reviewed by Me',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sorts(): array
    {
        return [
            'due' => 'Due soonest',
            'amount' => 'Highest amount',
            'oldest' => 'Oldest',
        ];
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'waiting';
    }

    /**
     * Reviewed by Me filter chips: key => label, with how many match the search.
     *
     * @return array<string, array{label: string, count: int}>
     */
    public function decisionFilters(): array
    {
        $decided = SampleRfps::decidedByMe()->filter($this->matchesSearch(...))->filter($this->matchesFilters(...));

        return collect(['all' => 'All', 'approved' => 'Approved', 'returned' => 'Returned', 'rejected' => 'Rejected'])
            ->map(fn (string $label, string $key): array => [
                'label' => $label,
                'count' => $key === 'all' ? $decided->count() : $decided->where('decision', $key)->count(),
            ])
            ->all();
    }

    public function setDecision(string $decision): void
    {
        $this->decision = array_key_exists($decision, $this->decisionFilters()) ? $decision : 'all';
    }

    /**
     * Departments of the requestors in the current tab, for the filter sheet.
     *
     * @return list<string>
     */
    public function departments(): array
    {
        $rows = $this->tab === 'done' ? SampleRfps::decidedByMe() : SampleRfps::awaitingMyApproval();

        return $rows->pluck('department')->filter()->unique()->sort()->values()->all();
    }

    /**
     * How many filters are set in the current tab, for the badge on the Filters button.
     */
    public function activeFilterCount(): int
    {
        $filters = [...self::NO_FILTERS, ...$this->filters];

        return collect([
            $this->tab === 'waiting' && filled($filters['due']),
            $this->tab === 'done' && filled($filters['decided']),
            filled($filters['type']),
            filled($filters['department']),
            filled($filters['min']) || filled($filters['max']),
        ])->filter()->count();
    }

    /**
     * Rows in the current tab after search and filters.
     */
    public function resultCount(): int
    {
        return $this->tab === 'done' ? $this->decided()->flatten(1)->count() : $this->waiting()->count();
    }

    public function resetFilters(): void
    {
        $this->filters = self::NO_FILTERS;
    }

    public function setSort(string $sort): void
    {
        $this->sort = array_key_exists($sort, $this->sorts()) ? $sort : 'due';
    }

    /**
     * @return array{count: int, amount: float, urgent: int}
     */
    public function summary(): array
    {
        $waiting = $this->awaiting();

        return [
            'count' => $waiting->count(),
            'amount' => $waiting->sum('amount'),
            'urgent' => $waiting->filter(fn (array $rfp): bool => $rfp['dueDate']->lte(today()))->count(),
        ];
    }

    /**
     * RFPs waiting on the signed-in approver, searched and sorted.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function waiting(): Collection
    {
        $waiting = $this->awaiting()->filter($this->matchesSearch(...))->filter($this->matchesFilters(...));

        $sorted = match ($this->sort) {
            'amount' => $waiting->sortByDesc('amount'),
            'oldest' => $waiting->sortBy('date'),
            default => $waiting->sortBy('dueDate'),
        };

        return $sorted->values();
    }

    /**
     * Mass approval limits from RFP Settings → Bulk Approval: `item` is the amount from
     * which an RFP must be approved one by one, `total` the most one batch may add up to.
     *
     * @return array{item: float, total: float}
     */
    #[Computed]
    public function bulkLimits(): array
    {
        $settings = Setting::current();

        return ['item' => $settings->bulk_approval_item_limit, 'total' => $settings->bulk_approval_total_limit];
    }

    /**
     * @param  array<string, mixed>  $rfp
     */
    public function canBulkApprove(array $rfp): bool
    {
        return $rfp['amount'] < $this->bulkLimits['item'];
    }

    /**
     * Amount of each RFP that can be mass approved, keyed by number, for the selection total.
     *
     * @return array<string, float>
     */
    public function bulkAmounts(): array
    {
        return $this->awaiting()->filter($this->canBulkApprove(...))->pluck('amount', 'number')->all();
    }

    /**
     * Approve several RFPs at once. Refused when one is a high amount, the batch adds up
     * to more than the total limit, or one is not waiting on this approver.
     *
     * @param  list<string>  $numbers
     */
    public function approveSelected(array $numbers): void
    {
        $rfps = $this->awaiting()->whereIn('number', $numbers);

        if ($rfps->isEmpty() || $rfps->count() !== count(array_unique($numbers)) || $rfps->contains(fn (array $rfp): bool => ! $this->canBulkApprove($rfp))) {
            Notification::make()
                ->title('Those RFPs can’t be approved together')
                ->body('RFPs of ₱'.number_format($this->bulkLimits['item'], 2).' and above must be approved one by one.')
                ->danger()
                ->send();

            return;
        }

        if ($rfps->sum('amount') > $this->bulkLimits['total']) {
            Notification::make()
                ->title('Over the bulk approval limit')
                ->body('Only a total of ₱'.number_format($this->bulkLimits['total'], 2).' can be approved at once. These add up to ₱'.number_format($rfps->sum('amount'), 2).': untick some and approve them in another batch.')
                ->danger()
                ->send();

            return;
        }

        $this->approved = [...$this->approved, ...$rfps->pluck('number')->all()];

        Notification::make()
            ->title($rfps->count() === 1 ? '1 RFP approved' : $rfps->count().' RFPs approved')
            ->body('₱'.number_format($rfps->sum('amount'), 2).' sent on to the next approver.')
            ->success()
            ->send();
    }

    /**
     * Past decisions, newest first, grouped by the month they were made.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function decided(): Collection
    {
        return SampleRfps::decidedByMe()
            ->filter($this->matchesSearch(...))
            ->filter($this->matchesFilters(...))
            ->filter(fn (array $rfp): bool => $this->decision === 'all' || $rfp['decision'] === $this->decision)
            ->sortByDesc('decidedAt')
            ->groupBy(fn (array $rfp): string => $rfp['decidedAt']->format('F Y'));
    }

    /**
     * Where a decided RFP is now, in a few words.
     *
     * @param  array<string, mixed>  $rfp
     */
    public static function whereNow(array $rfp): string
    {
        /** @var RfpStatus $status */
        $status = $rfp['status'];

        return match (true) {
            $status === RfpStatus::Completed => 'Paid '.$rfp['paidAt']->format('M j'),
            $status === RfpStatus::ForRevision => 'Back with the requestor',
            in_array($status, [RfpStatus::Rejected, RfpStatus::Cancelled], true) => 'Closed',
            MyRequests::stageOf($status) > 0 => 'Now in '.MyRequests::STAGES[MyRequests::stageOf($status) - 1],
            default => $status->getLabel(),
        };
    }

    /**
     * Waiting on the signed-in approver, minus what was just mass approved.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function awaiting(): Collection
    {
        return SampleRfps::awaitingMyApproval()->whereNotIn('number', $this->approved)->values();
    }

    /**
     * @param  array<string, mixed>  $rfp
     */
    protected function matchesFilters(array $rfp): bool
    {
        $filters = [...self::NO_FILTERS, ...$this->filters];
        $min = blank($filters['min']) ? null : CreateRfp::toAmount($filters['min']);
        $max = blank($filters['max']) ? null : CreateRfp::toAmount($filters['max']);

        /** @var RfpRequestType $type */
        $type = $rfp['type'];

        return match ($this->tab === 'waiting' ? $filters['due'] : null) {
            'overdue' => $rfp['dueDate']->lt(today()),
            'today' => $rfp['dueDate']->isToday(),
            'week' => $rfp['dueDate']->lte(today()->endOfWeek()),
            default => true,
        }
        && match ($this->tab === 'done' ? $filters['decided'] : null) {
            'month' => $rfp['decidedAt']->isSameMonth(now()),
            'quarter' => $rfp['decidedAt']->gte(now()->subMonths(3)->startOfDay()),
            default => true,
        }
        && (blank($filters['type']) || in_array($type->value, $filters['type'], true))
        && (blank($filters['department']) || in_array($rfp['department'], $filters['department'], true))
        && ($min === null || $rfp['amount'] >= $min)
        && ($max === null || $rfp['amount'] <= $max);
    }

    /**
     * @param  array<string, mixed>  $rfp
     */
    protected function matchesSearch(array $rfp): bool
    {
        $search = str($this->search)->trim()->lower()->toString();

        return $search === '' || str(implode(' ', [$rfp['number'], $rfp['payee'], $rfp['purpose'], $rfp['requestor']]))->lower()->contains($search);
    }
}
