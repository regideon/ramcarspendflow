<?php

namespace App\Filament\My\Pages;

use App\Enums\RfpStatus;
use App\Filament\My\Support\SampleRfps;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The signed-in user's own RFPs, grouped by month like a bank transaction history.
 *
 * Rows come from SampleRfps until the design is signed off; tabs and search already
 * filter them.
 */
class MyRequests extends Page
{
    protected static string $layout = 'filament.my.layout';

    protected string $view = 'filament.my.pages.my-requests';

    protected static ?string $slug = 'requests';

    protected static ?string $title = 'My requests';

    /**
     * The four stages a submitted RFP moves through, in order.
     *
     * @var list<string>
     */
    public const array STAGES = ['AP Review', 'Approval', 'AP Processing', 'Treasury'];

    #[Url]
    public string $tab = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    /**
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return [
            'all' => 'All',
            'drafts' => 'Drafts',
            'in_progress' => 'In progress',
            'for_revision' => 'For revision',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
        ];
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'all';
    }

    /**
     * @return array<string, int>
     */
    public function tabCounts(): array
    {
        $requests = $this->allRequests();

        return collect($this->tabs())
            ->map(fn (string $label, string $tab): int => $requests->filter(fn (array $request): bool => $this->inTab($request, $tab))->count())
            ->all();
    }

    /**
     * Filtered rows, grouped by the month they were submitted (or created, for drafts).
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function groupedRequests(): Collection
    {
        $search = str($this->search)->trim()->lower()->toString();

        return $this->allRequests()
            ->filter(fn (array $request): bool => $this->inTab($request, $this->tab))
            ->filter(fn (array $request): bool => $search === '' || str(implode(' ', [$request['number'], $request['payee'], $request['purpose']]))->lower()->contains($search))
            ->sortByDesc('date')
            ->groupBy(fn (array $request): string => $request['date']->format('F Y'));
    }

    /**
     * @return array{inProgressAmount: float, inProgress: int, toFix: int, paidAmount: float, paid: int, yearAmount: float, total: int}
     */
    public function summary(): array
    {
        $requests = $this->allRequests();
        $inProgress = $requests->filter(fn (array $request): bool => $request['status']->isAwaitingApproval());
        $paid = $requests->filter(fn (array $request): bool => $request['paidAt']?->isSameMonth(now()) ?? false);

        return [
            'inProgressAmount' => $inProgress->sum('amount'),
            'inProgress' => $inProgress->count(),
            'toFix' => $requests->where('status', RfpStatus::ForRevision)->count(),
            'paidAmount' => $paid->sum('amount'),
            'paid' => $paid->count(),
            'yearAmount' => $requests->filter(fn (array $request): bool => $request['date']->isSameYear(now()))->sum('amount'),
            'total' => $requests->count(),
        ];
    }

    /**
     * Which of the four stages the RFP is at (1-based); 0 before submission.
     */
    public static function stageOf(RfpStatus $status): int
    {
        return match ($status) {
            RfpStatus::ForApReview => 1,
            RfpStatus::ForApproval => 2,
            RfpStatus::ForApProcessing, RfpStatus::ForAccountingApproval => 3,
            RfpStatus::ForTreasury, RfpStatus::Completed => 4,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $request
     */
    protected function inTab(array $request, string $tab): bool
    {
        /** @var RfpStatus $status */
        $status = $request['status'];

        return match ($tab) {
            'drafts' => $status === RfpStatus::Draft,
            'in_progress' => $status->isAwaitingApproval(),
            'for_revision' => $status === RfpStatus::ForRevision,
            'completed' => $status === RfpStatus::Completed,
            'rejected' => in_array($status, [RfpStatus::Rejected, RfpStatus::Cancelled], true),
            default => true,
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function allRequests(): Collection
    {
        return SampleRfps::mine();
    }
}
