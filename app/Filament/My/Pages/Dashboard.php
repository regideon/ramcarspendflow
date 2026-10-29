<?php

namespace App\Filament\My\Pages;

use App\Enums\RfpStatus;
use App\Filament\My\Support\SampleRfps;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;

/**
 * Home for requestors and matrix approvers. Approvers see their approval queue first,
 * then their own requests; requestors see only their requests.
 *
 * Content comes from SampleRfps until the design is signed off.
 */
class Dashboard extends BaseDashboard
{
    protected static string $layout = 'filament.my.layout';

    protected string $view = 'filament.my.pages.dashboard';

    public function getTitle(): string
    {
        return 'Home';
    }

    public function isApprover(): bool
    {
        return (bool) auth()->user()?->isMatrixApprover();
    }

    public function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * @return array{amount: float, count: int, overdue: int, dueToday: int}
     */
    public function approvalSummary(): array
    {
        $approvals = SampleRfps::awaitingMyApproval();

        return [
            'amount' => $approvals->sum('amount'),
            'count' => $approvals->count(),
            'overdue' => $approvals->filter(fn (array $rfp): bool => $rfp['dueDate']->isBefore(today()))->count(),
            'dueToday' => $approvals->filter(fn (array $rfp): bool => $rfp['dueDate']->isToday())->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function approvals(): Collection
    {
        return SampleRfps::awaitingMyApproval()->sortBy('dueDate')->values();
    }

    /**
     * @return array{drafts: int, inProgress: int, completed: int, total: int}
     */
    public function requestSummary(): array
    {
        $mine = SampleRfps::mine();

        return [
            'drafts' => $mine->where('status', RfpStatus::Draft)->count(),
            'inProgress' => $mine->filter(fn (array $rfp): bool => $rfp['status']->isAwaitingApproval())->count(),
            'completed' => $mine->where('status', RfpStatus::Completed)->count(),
            'total' => $mine->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function myRequests(): Collection
    {
        return SampleRfps::mine()->sortByDesc('date')->take(3)->values();
    }
}
