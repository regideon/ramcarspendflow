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

    /**
     * Stops of the User Guide walkthrough, in order. `target` is the data-guide key the
     * view (or the layout) puts on that part of the screen; stops whose target is not
     * visible (e.g. the desktop sidebar on a phone) are skipped by the tour.
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        $approver = $this->isApprover();

        return array_values(array_filter([
            [
                'target' => 'greeting',
                'title' => 'Welcome to Ramcar AP',
                'body' => 'This is your home. Ramcar AP is where you raise Requests for Payment (RFPs) and follow them until they are paid. Tap your photo anytime to open your profile.',
            ],
            $approver ? [
                'target' => 'approval-summary',
                'title' => 'Awaiting your approval',
                'body' => 'The total amount and number of RFPs waiting for your decision. The red and amber tags count the ones already overdue or due today. Tap Review now to see the full list.',
            ] : null,
            $approver ? [
                'target' => 'approval-queue',
                'title' => 'Needs your action',
                'body' => 'Each card is one RFP for you to decide on, soonest due first. The dots show where it is in the approval matrix: it reaches you only after the approvers before you have approved. Tap Review to read it in full, or Approve to decide right away.',
            ] : null,
            [
                'target' => 'my-requests',
                'title' => 'My requests',
                'body' => 'The RFPs you raised, newest first. The colored pill shows the current status, such as Draft, For approval or Completed. Tap one to see its timeline and messages.',
            ],
            [
                'target' => 'request-tabs',
                'title' => 'Drafts, in progress, completed',
                'body' => 'Quick counts of your requests. Drafts are not submitted yet, In progress are moving through review and sign-off, and Completed are paid. Tap one to open that list.',
            ],
            [
                'target' => 'new-rfp',
                'title' => 'Raise a new RFP',
                'body' => 'Start a new Request for Payment here. Fill in the payee, amount and purpose, attach the supporting documents, then submit. You can also save it as a draft and finish later.',
            ],
            [
                'target' => 'navigation',
                'title' => 'Getting around',
                'body' => $approver
                    ? 'Move between Home, the RFPs waiting for your decision, your own requests and your profile. The number on a menu item is how many items are waiting for you.'
                    : 'Move between Home, your requests and your profile.',
            ],
            [
                'target' => 'notifications',
                'title' => 'Notifications',
                'body' => 'The bell tells you when something changes: an RFP is approved, returned for revision, rejected or paid, or someone mentions you. A red dot means there is something new.',
            ],
            [
                'target' => 'messages',
                'title' => 'Messages',
                'body' => 'Chat with the people working on your RFPs. Ask a question or answer one without leaving Ramcar AP.',
            ],
            [
                'target' => 'user-guide',
                'title' => 'Need this again?',
                'body' => 'Tap User Guide anytime to replay this walkthrough. Use the arrow keys to move between steps and Esc to close.',
            ],
        ]));
    }
}
