<?php

namespace App\Services\Rfp;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Data behind the Requestor dashboard (docs/dashboard-plan.md §4.1).
 *
 * STATIC SAMPLE DATA for now, shaped like the real aggregates will be. Each method is
 * replaced later by a query over `rfps`, `rfp_items` and `rfp_histories` scoped to
 * `$this->user`; the widgets only read these arrays.
 */
class RfpRequestorDashboard
{
    /**
     * Route stages in RfpProgress order; a status maps to its 1-based stage number.
     */
    public const array STAGES = [
        1 => 'New request',
        2 => 'AP review',
        3 => 'Approval',
        4 => 'AP processing',
        5 => 'Accounting approval',
        6 => 'Treasury',
        7 => 'Completed',
    ];

    public function __construct(public readonly User $user) {}

    public static function stageOf(RfpStatus $status): int
    {
        return match ($status) {
            RfpStatus::Draft, RfpStatus::ForRevision => 1,
            RfpStatus::ForApReview => 2,
            RfpStatus::ForApproval => 3,
            RfpStatus::ForApProcessing => 4,
            RfpStatus::ForAccountingApproval => 5,
            RfpStatus::ForTreasury => 6,
            RfpStatus::Completed, RfpStatus::Rejected, RfpStatus::Cancelled => 7,
        };
    }

    /**
     * Requests returned for revision, then unsubmitted drafts.
     *
     * @return list<array{rfp_number: string, status: RfpStatus, payee: string, amount: float, note: string, at: Carbon}>
     */
    public function actionNeeded(): array
    {
        return [
            ['rfp_number' => 'RFP-2026-000142', 'status' => RfpStatus::ForRevision, 'payee' => 'Dentsu Creative Philippines', 'amount' => 145000.00, 'note' => 'Attach the official receipt for line 2.', 'at' => now()->subDays(2)],
            ['rfp_number' => 'RFP-2026-000145', 'status' => RfpStatus::ForRevision, 'payee' => $this->user->name.' (Reimbursement)', 'amount' => 18450.00, 'note' => 'Itemized receipts are missing for the client dinner.', 'at' => now()->subDays(4)],
            ['rfp_number' => 'RFP-2026-000150', 'status' => RfpStatus::Draft, 'payee' => 'Globe Telecom Corporate', 'amount' => 42300.00, 'note' => 'Draft saved yesterday. No supporting documents attached yet.', 'at' => now()->subDay()],
        ];
    }

    /**
     * @return array{revision: int, drafts: int, in_progress: int, in_progress_amount: float, with_approvers: int, with_accounting: int, with_treasury: int, paid_count: int, paid_amount: float, paid_change: int, avg_days: float, avg_days_change: float}
     */
    public function stats(): array
    {
        return [
            'revision' => 2,
            'drafts' => 3,
            'in_progress' => 8,
            'in_progress_amount' => 486250.00,
            'with_approvers' => 3,
            'with_accounting' => 4,
            'with_treasury' => 1,
            'paid_count' => 12,
            'paid_amount' => 1320000.00,
            'paid_change' => 18,
            'avg_days' => 9.4,
            'avg_days_change' => -1.2,
        ];
    }

    /**
     * My recent requests (open ones and the latest finished).
     *
     * @return list<array{rfp_number: string, type: RfpRequestType, payee: string, purpose: string, amount: float, foreign: ?string, status: RfpStatus, with: ?string, days_at_step: int, due_date: Carbon}>
     */
    public function requestsInFlight(): array
    {
        return [
            ['rfp_number' => 'RFP-2026-000150', 'type' => RfpRequestType::Standard, 'payee' => 'Globe Telecom Corporate', 'purpose' => 'Head office mobile plans', 'amount' => 42300.00, 'foreign' => null, 'status' => RfpStatus::Draft, 'with' => null, 'days_at_step' => 1, 'due_date' => today()->addDays(12)],
            ['rfp_number' => 'RFP-2026-000145', 'type' => RfpRequestType::Reimbursement, 'payee' => $this->user->name, 'purpose' => 'Client dinner, Makati', 'amount' => 18450.00, 'foreign' => null, 'status' => RfpStatus::ForRevision, 'with' => null, 'days_at_step' => 4, 'due_date' => today()->addDays(4)],
            ['rfp_number' => 'RFP-2026-000142', 'type' => RfpRequestType::Standard, 'payee' => 'Dentsu Creative Philippines', 'purpose' => 'Q4 campaign creatives', 'amount' => 145000.00, 'foreign' => null, 'status' => RfpStatus::ForRevision, 'with' => null, 'days_at_step' => 2, 'due_date' => today()->addDays(7)],
            ['rfp_number' => 'RFP-2026-000148', 'type' => RfpRequestType::Standard, 'payee' => 'Fast Logistics Corp', 'purpose' => 'Cebu distribution haulage', 'amount' => 88400.00, 'foreign' => null, 'status' => RfpStatus::ForApReview, 'with' => RoleName::ApProcessor->value, 'days_at_step' => 1, 'due_date' => today()->addDays(9)],
            ['rfp_number' => 'RFP-2026-000146', 'type' => RfpRequestType::Reimbursement, 'payee' => $this->user->name, 'purpose' => 'Davao trade show booth incidentals', 'amount' => 24150.00, 'foreign' => null, 'status' => RfpStatus::ForApproval, 'with' => RoleName::DepartmentHead->value, 'days_at_step' => 4, 'due_date' => today()->subDays(3)],
            ['rfp_number' => 'RFP-2026-000144', 'type' => RfpRequestType::Standard, 'payee' => 'AWS Cloud Services', 'purpose' => 'Monthly cloud server hosting', 'amount' => 215600.00, 'foreign' => 'USD 3,850.00', 'status' => RfpStatus::ForApProcessing, 'with' => RoleName::ApProcessor->value, 'days_at_step' => 2, 'due_date' => today()->addDays(6)],
            ['rfp_number' => 'RFP-2026-000141', 'type' => RfpRequestType::Standard, 'payee' => 'SGV & Co.', 'purpose' => 'Interim audit tax advisory retainer', 'amount' => 350000.00, 'foreign' => null, 'status' => RfpStatus::ForAccountingApproval, 'with' => RoleName::ApManager->value, 'days_at_step' => 6, 'due_date' => today()->addDays(3)],
            ['rfp_number' => 'RFP-2026-000138', 'type' => RfpRequestType::Standard, 'payee' => 'Meralco', 'purpose' => 'Laguna warehouse electricity', 'amount' => 124800.00, 'foreign' => null, 'status' => RfpStatus::ForTreasury, 'with' => RoleName::Treasury->value, 'days_at_step' => 1, 'due_date' => today()->addDays(5)],
            ['rfp_number' => 'RFP-2026-000135', 'type' => RfpRequestType::Standard, 'payee' => 'PLDT Enterprise', 'purpose' => 'Leased line fiber connectivity', 'amount' => 45000.00, 'foreign' => null, 'status' => RfpStatus::Completed, 'with' => null, 'days_at_step' => 0, 'due_date' => today()->subDays(8)],
        ];
    }

    /**
     * Open requests per stage (count and PHP value).
     *
     * @return list<array{stage: string, count: int, amount: float}>
     */
    public function pipeline(): array
    {
        return [
            ['stage' => 'AP review', 'count' => 2, 'amount' => 145200.00],
            ['stage' => 'Approval', 'count' => 3, 'amount' => 196400.00],
            ['stage' => 'AP processing', 'count' => 1, 'amount' => 215600.00],
            ['stage' => 'Accounting approval', 'count' => 1, 'amount' => 350000.00],
            ['stage' => 'Treasury', 'count' => 1, 'amount' => 124800.00],
        ];
    }

    /**
     * Average days spent per stage over the last 90 days, longest first.
     *
     * @return list<array{stage: string, days: float}>
     */
    public function stageWait(): array
    {
        return [
            ['stage' => 'Approval', 'days' => 3.8],
            ['stage' => 'Accounting approval', 'days' => 2.4],
            ['stage' => 'AP review', 'days' => 1.8],
            ['stage' => 'AP processing', 'days' => 1.5],
            ['stage' => 'Treasury', 'days' => 1.1],
        ];
    }

    /**
     * Submitted vs paid PHP per month, oldest first.
     *
     * @return list<array{month: string, submitted: float, paid: float}>
     */
    public function spendByMonth(): array
    {
        $values = [[1.02, 0.86], [1.18, 1.07], [1.52, 1.41], [0.79, 0.68], [1.13, 1.01], [1.20, 1.18]];

        return collect($values)->values()->map(fn (array $pair, int $i): array => [
            'month' => now()->startOfMonth()->subMonths(5 - $i)->format('M'),
            'submitted' => $pair[0] * 1_000_000,
            'paid' => $pair[1] * 1_000_000,
        ])->all();
    }

    /**
     * Open requests due within 14 days, overdue first.
     *
     * @return list<array{rfp_number: string, payee: string, purpose: string, amount: float, due_date: Carbon, status: RfpStatus}>
     */
    public function upcomingDue(): array
    {
        return collect($this->requestsInFlight())
            ->filter(fn (array $row): bool => $row['status']->isAwaitingApproval())
            ->filter(fn (array $row): bool => $row['due_date']->lte(today()->addDays(14)))
            ->sortBy(fn (array $row): int => $row['due_date']->getTimestamp())
            ->map(fn (array $row): array => collect($row)->only(['rfp_number', 'payee', 'purpose', 'amount', 'due_date', 'status'])->all())
            ->values()
            ->all();
    }

    /**
     * Reasons AP sent my requests back, from `for_revision` history remarks.
     *
     * @return list<array{reason: string, count: int}>
     */
    public function returnReasons(): array
    {
        return [
            ['reason' => 'Missing official receipt', 'count' => 5],
            ['reason' => 'Wrong cost center', 'count' => 3],
            ['reason' => 'Amount does not match invoice', 'count' => 2],
        ];
    }

    /**
     * My spend by RFP category for the period.
     *
     * @return list<array{category: string, amount: float}>
     */
    public function categories(): array
    {
        return [
            ['category' => 'Marketing & Advertising', 'amount' => 792000.00],
            ['category' => 'Software & IT', 'amount' => 504000.00],
            ['category' => 'Travel', 'amount' => 288000.00],
            ['category' => 'Office & Admin', 'amount' => 216000.00],
        ];
    }

    /**
     * Latest history rows on my requests.
     *
     * @return list<array{rfp_number: string, text: string, tone: string, at: Carbon}>
     */
    public function activity(): array
    {
        return [
            ['rfp_number' => 'RFP-2026-000139', 'text' => 'approved by Department Head', 'tone' => 'success', 'at' => now()->subHour()],
            ['rfp_number' => 'RFP-2026-000131', 'text' => 'paid by Treasury', 'tone' => 'info', 'at' => now()->subDay()],
            ['rfp_number' => 'RFP-2026-000142', 'text' => 'returned for revision by AP Processor', 'tone' => 'warning', 'at' => now()->subDays(2)],
            ['rfp_number' => 'RFP-2026-000146', 'text' => 'approved by Line Manager', 'tone' => 'success', 'at' => now()->subDays(4)],
        ];
    }
}
