<?php

namespace App\Services\Accounting;

use App\Enums\P2pThreeWayMatchStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Data behind the AP Processor dashboard (docs/dashboard-plan.md §4.3): one work queue
 * across RFP (Step 2 first review, Step 4 processing) and P2P (invoices submitted to AP).
 *
 * STATIC SAMPLE DATA for now, shaped like the real aggregates will be. Each method is
 * replaced later by queries over `rfps` / `rfp_histories` and `p2p_invoices` /
 * `p2p_escalations` / `p2p_approval_histories`, limited to the user's companies.
 */
class ApProcessorDashboard
{
    public const string TASK_FIRST_REVIEW = 'First review';

    public const string TASK_TAX = 'Tax & WHT entry';

    public const string TASK_REWORK = 'Rework: supervisor remarks';

    public const string TASK_ESCALATION = 'Escalation resolved';

    /**
     * Work-queue tabs: key => label. A row can sit in several (e.g. returned and overdue).
     */
    public const array TABS = [
        'rfp_review' => 'RFP first review',
        'rfp_processing' => 'RFP processing',
        'p2p' => 'P2P to process',
        'returned' => 'Returned to me',
        'overdue' => 'Overdue',
    ];

    public function __construct(public readonly User $user) {}

    /**
     * Everything waiting on me, and how much of it is past due.
     *
     * @return array{total: int, overdue: int}
     */
    public function queueSize(): array
    {
        return ['total' => 18, 'overdue' => 4];
    }

    /**
     * Items handed back to me: returned by the AP Supervisor, or an escalation resolved.
     *
     * @return list<array{module: string, reference: string, by: string, remark: string, amount: float, at: Carbon, action: string}>
     */
    public function backOnDesk(): array
    {
        return [
            ['module' => 'RFP', 'reference' => 'RFP-2026-000127', 'by' => 'returned by AP Supervisor', 'remark' => 'WHT base excludes VAT, please recompute.', 'amount' => 312500.00, 'at' => now()->subDay(), 'action' => 'Rework'],
            ['module' => 'P2P', 'reference' => 'GRC-00000219', 'by' => 'returned by AP Supervisor', 'remark' => 'Wrong tax code for imported goods.', 'amount' => 88940.00, 'at' => now()->subHours(3), 'action' => 'Rework'],
            ['module' => 'P2P', 'reference' => 'ESC-000041', 'by' => 'resolved by Procurement', 'remark' => 'GRC-00000207 is back in your queue.', 'amount' => 145200.00, 'at' => now()->subDay(), 'action' => 'Continue'],
        ];
    }

    /**
     * @return array{rfp_review: array{count: int, amount: float, oldest: int}, rfp_processing: array{count: int, amount: float, oldest: int}, p2p: array{count: int, amount: float, no_match: int}, returned: array{count: int, amount: float, rfp: int, p2p: int}, overdue: array{count: int, amount: float}, processed: array{count: int, amount: float, avg_days: float, avg_change: float}}
     */
    public function stats(): array
    {
        return [
            'rfp_review' => ['count' => 5, 'amount' => 642300.00, 'oldest' => 3],
            'rfp_processing' => ['count' => 4, 'amount' => 1180000.00, 'oldest' => 6],
            'p2p' => ['count' => 7, 'amount' => 2060000.00, 'no_match' => 2],
            'returned' => ['count' => 2, 'amount' => 401440.00, 'rfp' => 1, 'p2p' => 1],
            'overdue' => ['count' => 4, 'amount' => 415800.00],
            'processed' => ['count' => 31, 'amount' => 4820000.00, 'avg_days' => 1.6, 'avg_change' => -0.3],
        ];
    }

    /**
     * Items I processed per day this week, oldest first (the "Processed this week" sparkline).
     *
     * @return list<int>
     */
    public function processedPerDay(): array
    {
        return [4, 6, 5, 7, 6, 8, 9];
    }

    /**
     * The work queue, most urgent first. `checks` are chips (label, color); `match`
     * and `ai_read` only apply to P2P rows.
     *
     * @return list<array{module: string, reference: string, sub: string, task: string, party: string, company: string, description: string, amount: float, foreign: ?string, checks: list<array{0: string, 1: string}>, match: ?P2pThreeWayMatchStatus, ai_read: ?string, due_date: Carbon, age: int, tabs: list<string>}>
     */
    public function queue(): array
    {
        $rows = [
            ['module' => 'RFP', 'reference' => 'RFP-2026-000127', 'sub' => 'Step 4 · AP processing', 'task' => self::TASK_REWORK, 'party' => 'Dentsu Creative Philippines', 'company' => 'OMMC', 'description' => 'Q3 advertising campaign', 'amount' => 312500.00, 'foreign' => null, 'checks' => [['Pre-approved', 'primary']], 'match' => null, 'ai_read' => null, 'due_date' => today()->subDays(2), 'age' => 4, 'tabs' => ['rfp_processing', 'returned']],
            ['module' => 'P2P', 'reference' => 'GRC-00000233', 'sub' => 'PO 4500098412', 'task' => self::TASK_TAX, 'party' => 'Northstar Industrial Supply', 'company' => 'PBI', 'description' => 'Battery terminal moulds', 'amount' => 215600.00, 'foreign' => 'USD 3,850.00', 'checks' => [], 'match' => P2pThreeWayMatchStatus::NoMatch, 'ai_read' => 'AI read: price differs on line 2', 'due_date' => today()->subDay(), 'age' => 3, 'tabs' => ['p2p']],
            ['module' => 'P2P', 'reference' => 'GRC-00000219', 'sub' => 'PO 4500097720', 'task' => self::TASK_REWORK, 'party' => 'Pilipinas Shell', 'company' => 'PBI', 'description' => 'Industrial lubricants', 'amount' => 88940.00, 'foreign' => null, 'checks' => [], 'match' => P2pThreeWayMatchStatus::Matched, 'ai_read' => 'AI read: 2/2 lines matched', 'due_date' => today(), 'age' => 1, 'tabs' => ['p2p', 'returned']],
            ['module' => 'RFP', 'reference' => 'RFP-2026-000151', 'sub' => 'Step 2 · First review', 'task' => self::TASK_FIRST_REVIEW, 'party' => 'Maria Santos', 'company' => 'RTI', 'description' => 'Plant visit travel and client dinner', 'amount' => 24150.00, 'foreign' => null, 'checks' => [['Reimbursement', 'info']], 'match' => null, 'ai_read' => null, 'due_date' => today()->addDay(), 'age' => 1, 'tabs' => ['rfp_review']],
            ['module' => 'RFP', 'reference' => 'RFP-2026-000148', 'sub' => 'Step 4 · AP processing', 'task' => self::TASK_TAX, 'party' => 'Fast Logistics Corp', 'company' => 'PBI', 'description' => 'Inter-island battery haulage', 'amount' => 88400.00, 'foreign' => null, 'checks' => [['Standard', 'gray'], ['Direct to VP', 'info']], 'match' => null, 'ai_read' => null, 'due_date' => today()->subDays(3), 'age' => 6, 'tabs' => ['rfp_processing']],
            ['module' => 'P2P', 'reference' => 'GRC-00000234', 'sub' => 'PO 4500098810', 'task' => self::TASK_TAX, 'party' => 'Meralco', 'company' => 'PBI', 'description' => 'Plant electricity', 'amount' => 1240000.00, 'foreign' => null, 'checks' => [], 'match' => P2pThreeWayMatchStatus::Matched, 'ai_read' => 'AI read: 5/5 lines matched', 'due_date' => today()->addDays(3), 'age' => 2, 'tabs' => ['p2p']],
            ['module' => 'P2P', 'reference' => 'GRC-00000207', 'sub' => 'PO 4500096500', 'task' => self::TASK_ESCALATION, 'party' => 'PLDT Enterprise', 'company' => 'EERI', 'description' => 'Leased line connectivity', 'amount' => 145200.00, 'foreign' => null, 'checks' => [['ESC-000041 resolved', 'info']], 'match' => P2pThreeWayMatchStatus::Matched, 'ai_read' => null, 'due_date' => today()->addDays(2), 'age' => 3, 'tabs' => ['p2p']],
            ['module' => 'RFP', 'reference' => 'RFP-2026-000155', 'sub' => 'Step 2 · First review', 'task' => self::TASK_FIRST_REVIEW, 'party' => 'SGV & Co.', 'company' => 'PBI', 'description' => 'Interim audit fees', 'amount' => 350000.00, 'foreign' => null, 'checks' => [['Standard', 'gray']], 'match' => null, 'ai_read' => null, 'due_date' => today()->addDays(4), 'age' => 0, 'tabs' => ['rfp_review']],
        ];

        return array_map(function (array $row): array {
            if ($row['due_date']->lt(today())) {
                $row['tabs'][] = 'overdue';
            }

            return $row;
        }, $rows);
    }

    /**
     * Items in my queue by age bucket, split by module.
     *
     * @return list<array{bucket: string, rfp: int, p2p: int, color: string}>
     */
    public function aging(): array
    {
        return [
            ['bucket' => '0–2 days', 'rfp' => 4, 'p2p' => 4, 'color' => 'gray'],
            ['bucket' => '3–5 days', 'rfp' => 3, 'p2p' => 2, 'color' => 'info'],
            ['bucket' => '6–10 days', 'rfp' => 1, 'p2p' => 2, 'color' => 'warning'],
            ['bucket' => '11+ days', 'rfp' => 1, 'p2p' => 1, 'color' => 'danger'],
        ];
    }

    /**
     * Items received into my queue vs items I completed, per day for 14 days.
     *
     * @return list<array{day: string, received: int, completed: int}>
     */
    public function throughput(): array
    {
        $received = [6, 5, 8, 5, 9, 7, 6, 7, 4, 7, 8, 6, 6, 7];
        $completed = [7, 7, 8, 6, 8, 9, 6, 8, 7, 9, 9, 8, 8, 9];

        return array_map(fn (int $i): array => [
            'day' => today()->subDays(13 - $i)->format('M j'),
            'received' => $received[$i],
            'completed' => $completed[$i],
        ], range(0, 13));
    }

    /**
     * P2P escalations I raised that are still open.
     *
     * @return list<array{number: string, grc: string, reason: string, route: list<string>, current: int, at: Carbon}>
     */
    public function escalations(): array
    {
        return [
            ['number' => 'ESC-000046', 'grc' => 'GRC-00000211', 'reason' => 'Price difference', 'route' => ['Procurement', 'Warehouse', 'Procurement', 'Vendor'], 'current' => 0, 'at' => now()->subDays(4)],
            ['number' => 'ESC-000048', 'grc' => 'GRC-00000229', 'reason' => 'Other', 'route' => ['Procurement', 'Warehouse', 'You'], 'current' => 1, 'at' => now()->subDays(2)],
        ];
    }

    /**
     * @return array{avg_days: float}
     */
    public function escalationSummary(): array
    {
        return ['avg_days' => 3.4];
    }

    /**
     * VAT / EWT totals of invoices I processed this period, and missing tax codes.
     *
     * @return array{vatable_sales: float, vat_exempt: float, vat_zero_rated: float, vat: float, ewt: float, net: float, missing_wht: int}
     */
    public function taxSummary(): array
    {
        return [
            'vatable_sales' => 3840250.00,
            'vat_exempt' => 420000.00,
            'vat_zero_rated' => 180000.00,
            'vat' => 460830.00,
            'ewt' => 68405.00,
            'net' => 4832675.00,
            'missing_wht' => 3,
        ];
    }

    /**
     * What I did with the items I finished this period.
     *
     * @return list<array{label: string, count: int, color: string}>
     */
    public function decisions(): array
    {
        return [
            ['label' => 'Sent to AP Supervisor', 'count' => 64, 'color' => 'success'],
            ['label' => 'For Revision (RFP)', 'count' => 9, 'color' => 'info'],
            ['label' => 'Returned to Vendor (P2P)', 'count' => 5, 'color' => 'warning'],
            ['label' => 'Escalated (P2P)', 'count' => 4, 'color' => 'primary'],
            ['label' => 'Cancelled (RFP)', 'count' => 2, 'color' => 'danger'],
        ];
    }

    /**
     * Share of my work the AP Supervisor sent back, and the change vs last period.
     *
     * @return array{rate: float, change: float}
     */
    public function supervisorReturnRate(): array
    {
        return ['rate' => 9.4, 'change' => -2.4];
    }

    /**
     * Why the AP Supervisor returned my work, from the return remarks.
     *
     * @return list<array{reason: string, count: int}>
     */
    public function returnReasons(): array
    {
        return [
            ['reason' => 'Wrong tax code', 'count' => 3],
            ['reason' => 'WHT base includes VAT', 'count' => 2],
            ['reason' => 'Missing attachment', 'count' => 1],
        ];
    }

    /**
     * @return list<array{text: string, tone: string, at: Carbon}>
     */
    public function activity(): array
    {
        return [
            ['text' => 'You sent RFP-2026-000118 to AP Supervisor', 'tone' => 'success', 'at' => now()->subMinutes(20)],
            ['text' => 'You submitted GRC-00000226 to Checker', 'tone' => 'success', 'at' => now()->subHour()],
            ['text' => 'RFP-2026-000127 returned by AP Supervisor', 'tone' => 'warning', 'at' => now()->subDay()],
            ['text' => 'ESC-000041 resolved by Procurement', 'tone' => 'info', 'at' => now()->subDay()->subHours(5)],
        ];
    }
}
