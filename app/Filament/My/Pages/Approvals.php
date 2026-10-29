<?php

namespace App\Filament\My\Pages;

use App\Enums\RfpStatus;
use App\Filament\My\Support\SampleRfps;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
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

    #[Url(as: 'q')]
    public string $search = '';

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
            'waiting' => 'Waiting for me',
            'done' => 'Done by me',
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

    public function setSort(string $sort): void
    {
        $this->sort = array_key_exists($sort, $this->sorts()) ? $sort : 'due';
    }

    /**
     * @return array{count: int, amount: float, urgent: int}
     */
    public function summary(): array
    {
        $waiting = SampleRfps::awaitingMyApproval();

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
        $waiting = SampleRfps::awaitingMyApproval()->filter($this->matchesSearch(...));

        $sorted = match ($this->sort) {
            'amount' => $waiting->sortByDesc('amount'),
            'oldest' => $waiting->sortBy('date'),
            default => $waiting->sortBy('dueDate'),
        };

        return $sorted->values();
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
     * @param  array<string, mixed>  $rfp
     */
    protected function matchesSearch(array $rfp): bool
    {
        $search = str($this->search)->trim()->lower()->toString();

        return $search === '' || str(implode(' ', [$rfp['number'], $rfp['payee'], $rfp['purpose'], $rfp['requestor']]))->lower()->contains($search);
    }
}
