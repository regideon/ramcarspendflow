<?php

namespace App\Services\P2p;

use App\Enums\P2pDownPaymentStatus;
use App\Enums\P2pStatus;
use App\Enums\P2pThreeWayMatchStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Data behind the Vendor dashboard (docs/dashboard-plan.md §4.2).
 *
 * STATIC SAMPLE DATA for now, shaped like the real aggregates will be. Each method is
 * replaced later by a query over `p2p_invoices`, `p2p_good_receipt_states` and
 * `p2p_escalations` scoped to the vendor's own records (`Invoice::visibleTo`).
 *
 * Vendors never see GR pricing (see BulkGoodsReceipt::hidePricing()), so goods
 * receipts carry quantities only; peso amounts are the vendor's own invoice figures.
 */
class P2pVendorDashboard
{
    /**
     * The invoice journey as the vendor sees it; 1-based stage numbers.
     */
    public const array STAGES = [
        1 => 'Goods received',
        2 => 'Invoice submitted',
        3 => 'AP review',
        4 => 'Accounting approval',
        5 => 'Parked in SAP',
        6 => 'Posted',
        7 => 'Paid',
    ];

    /**
     * Table tabs: key => label. Every status belongs to exactly one.
     */
    public const array GROUPS = [
        'action' => 'Needs my action',
        'review' => 'In review',
        'hold' => 'On hold',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'closed' => 'Cancelled',
    ];

    public function __construct(public readonly User $user) {}

    /**
     * Vendor-friendly view of a P2P status: internal steps (Checker, escalation hops,
     * returns between AP staff) are never named to the vendor.
     *
     * @return array{stage: int, label: string, color: string, group: string}
     */
    public static function vendorView(P2pStatus $status): array
    {
        return match ($status) {
            P2pStatus::Pending => ['stage' => 2, 'label' => 'Not yet submitted', 'color' => 'warning', 'group' => 'action'],
            P2pStatus::ReturnedToVendor => ['stage' => 2, 'label' => 'Returned to you', 'color' => 'warning', 'group' => 'action'],
            P2pStatus::EscalatedByVendor => ['stage' => 2, 'label' => 'On hold – escalated', 'color' => 'danger', 'group' => 'hold'],
            P2pStatus::SubmittedToAp, P2pStatus::ApReview, P2pStatus::ReturnedToApProcessor => ['stage' => 3, 'label' => 'In review', 'color' => 'info', 'group' => 'review'],
            P2pStatus::EscalatedByProcessor, P2pStatus::EscalateToProcurement, P2pStatus::EscalatedToWarehouse, P2pStatus::PartiallyEscalated => ['stage' => 3, 'label' => 'On hold – escalated', 'color' => 'danger', 'group' => 'hold'],
            P2pStatus::SubmittedToChecker, P2pStatus::CheckerReview => ['stage' => 4, 'label' => 'In review', 'color' => 'info', 'group' => 'review'],
            P2pStatus::Approved, P2pStatus::InvoiceParked, P2pStatus::PendingPost => ['stage' => 5, 'label' => 'Approved', 'color' => 'primary', 'group' => 'approved'],
            P2pStatus::InvoicePosted => ['stage' => 6, 'label' => 'Approved – awaiting payment', 'color' => 'primary', 'group' => 'approved'],
            P2pStatus::Completed => ['stage' => 7, 'label' => 'Paid', 'color' => 'success', 'group' => 'paid'],
            P2pStatus::Rejected, P2pStatus::RejectedLegacy => ['stage' => 2, 'label' => 'Rejected', 'color' => 'danger', 'group' => 'closed'],
            P2pStatus::Cancelled => ['stage' => 2, 'label' => 'Cancelled', 'color' => 'gray', 'group' => 'closed'],
            P2pStatus::Reversed => ['stage' => 2, 'label' => 'Reversed', 'color' => 'gray', 'group' => 'closed'],
        };
    }

    public function vendorName(): string
    {
        return $this->user->vendor?->name ?? $this->user->name;
    }

    public function vendorCode(): ?string
    {
        return $this->user->vendor?->code;
    }

    public function lastSyncedAt(): Carbon
    {
        return today()->setTime(8, 0);
    }

    /**
     * @return array{returned: list<array{grc: string, po: string, amount: float, remark: string, at: Carbon}>, ready: array{count: int, due_soon: int}, escalations: list<array{number: string, reason: string, gr: string, company: string}>}
     */
    public function actionNeeded(): array
    {
        return [
            'returned' => [
                ['grc' => 'GRC-00000231', 'po' => '4500098124', 'amount' => 348500.00, 'remark' => "Invoice date doesn't match the PDF.", 'at' => now()->subDay()],
                ['grc' => 'GRC-00000222', 'po' => '4500097109', 'amount' => 88000.00, 'remark' => 'Reference number is missing on the invoice.', 'at' => now()->subDays(3)],
            ],
            'ready' => ['count' => 5, 'due_soon' => 2],
            'escalations' => [
                ['number' => 'ESC-000045', 'reason' => 'Quantity difference', 'gr' => '5000123410', 'company' => 'PBI'],
            ],
        ];
    }

    /**
     * @return array{ready_grs: int, ready_lines: int, due_soon: int, review_count: int, review_amount: float, hold_count: int, hold_amount: float, approved_count: int, approved_amount: float, paid_count: int, paid_net: float, paid_change: int}
     */
    public function stats(): array
    {
        return [
            'ready_grs' => 14,
            'ready_lines' => 31,
            'due_soon' => 2,
            'review_count' => 6,
            'review_amount' => 1120000.00,
            'hold_count' => 2,
            'hold_amount' => 186400.00,
            'approved_count' => 4,
            'approved_amount' => 918250.00,
            'paid_count' => 9,
            'paid_net' => 3412600.00,
            'paid_change' => 12,
        ];
    }

    /**
     * Pending goods receipts, oldest "bill by" first. Quantities only, no pricing.
     *
     * @return list<array{gr: string, partial: bool, po: string, po_item: string, material: string, material_code: string, quantity: float, uom: string, gr_date: Carbon, bill_by: Carbon, company: string}>
     */
    public function readyToBill(): array
    {
        return [
            ['gr' => '5000123456-P1', 'partial' => true, 'po' => '4500098005', 'po_item' => '20', 'material' => 'Sulfuric acid 98%, IBC tote', 'material_code' => 'CHM-1008-ACD', 'quantity' => 8000, 'uom' => 'L', 'gr_date' => today()->subDays(9), 'bill_by' => today()->subDay(), 'company' => 'OMMC'],
            ['gr' => '5000123477', 'partial' => false, 'po' => '4500098124', 'po_item' => '10', 'material' => 'Battery plates, Grade A positive', 'material_code' => 'MAT-9902-BAT', 'quantity' => 1200, 'uom' => 'PC', 'gr_date' => today()->subDays(6), 'bill_by' => today()->addDays(2), 'company' => 'PBI'],
            ['gr' => '5000123488', 'partial' => false, 'po' => '4500098210', 'po_item' => '10', 'material' => 'Polypropylene battery casing, NS70', 'material_code' => 'CAS-4420-PP', 'quantity' => 2500, 'uom' => 'PC', 'gr_date' => today()->subDays(4), 'bill_by' => today()->addDays(5), 'company' => 'PBI'],
            ['gr' => '5000123490', 'partial' => false, 'po' => '4500098319', 'po_item' => '40', 'material' => 'Traction battery cells, 48V', 'material_code' => 'TRC-8012-IND', 'quantity' => 40, 'uom' => 'SET', 'gr_date' => today()->subDays(3), 'bill_by' => today()->addDays(7), 'company' => 'RTI'],
            ['gr' => '5000123502', 'partial' => false, 'po' => '4500098402', 'po_item' => '10', 'material' => 'Pure lead ingots 99.97%', 'material_code' => 'MET-5510-PB', 'quantity' => 3000, 'uom' => 'KG', 'gr_date' => today()->subDays(2), 'bill_by' => today()->addDays(8), 'company' => 'OMMC'],
        ];
    }

    /**
     * Net payable grouped by where the money is, for approved and paid invoices.
     *
     * @return list<array{label: string, hint: string, count: int, net: float, color: string}>
     */
    public function paymentOutlook(): array
    {
        return [
            ['label' => 'Paid this week', 'hint' => 'Clearing document received from SAP', 'count' => 3, 'net' => 1420000.00, 'color' => 'success'],
            ['label' => 'Posted, awaiting payment', 'hint' => 'FI document posted in SAP', 'count' => 2, 'net' => 598250.00, 'color' => 'primary'],
            ['label' => 'Approved, being parked', 'hint' => 'Approved by Ramcar Accounting', 'count' => 2, 'net' => 320000.00, 'color' => 'info'],
        ];
    }

    /**
     * My recent invoices (GRC submissions). `net` is null until AP assigns the tax codes.
     *
     * @return list<array{grc: string, invoice_no: string, po: string, company: string, invoice_date: Carbon, amount: float, net: ?float, match: P2pThreeWayMatchStatus, status: P2pStatus, days: int, sap_ref: ?string, note: ?string}>
     */
    public function invoices(): array
    {
        return [
            ['grc' => 'GRC-00000231', 'invoice_no' => 'INV-2026-8819', 'po' => '4500098124', 'company' => 'PBI', 'invoice_date' => today()->subDays(5), 'amount' => 348500.00, 'net' => null, 'match' => P2pThreeWayMatchStatus::ForVerification, 'status' => P2pStatus::ReturnedToVendor, 'days' => 1, 'sap_ref' => null, 'note' => "Invoice date doesn't match the PDF."],
            ['grc' => 'GRC-00000230', 'invoice_no' => 'INV-2026-8815', 'po' => '4500098001', 'company' => 'OMMC', 'invoice_date' => today()->subDays(4), 'amount' => 412000.00, 'net' => 403760.00, 'match' => P2pThreeWayMatchStatus::Matched, 'status' => P2pStatus::SubmittedToAp, 'days' => 1, 'sap_ref' => null, 'note' => null],
            ['grc' => 'GRC-00000229', 'invoice_no' => 'INV-2026-8802', 'po' => '4500097940', 'company' => 'PBI', 'invoice_date' => today()->subDays(7), 'amount' => 98400.00, 'net' => 96432.00, 'match' => P2pThreeWayMatchStatus::NoMatch, 'status' => P2pStatus::EscalatedToWarehouse, 'days' => 4, 'sap_ref' => null, 'note' => 'ESC-000045'],
            ['grc' => 'GRC-00000227', 'invoice_no' => 'INV-2026-8790', 'po' => '4500097720', 'company' => 'RTI', 'invoice_date' => today()->subDays(9), 'amount' => 520000.00, 'net' => 509600.00, 'match' => P2pThreeWayMatchStatus::Matched, 'status' => P2pStatus::InvoicePosted, 'days' => 2, 'sap_ref' => 'FI 1900004512', 'note' => null],
            ['grc' => 'GRC-00000226', 'invoice_no' => 'INV-2026-8781', 'po' => '4500097688', 'company' => 'PBI', 'invoice_date' => today()->subDays(10), 'amount' => 156000.00, 'net' => 152880.00, 'match' => P2pThreeWayMatchStatus::OpenPo, 'status' => P2pStatus::SubmittedToChecker, 'days' => 3, 'sap_ref' => null, 'note' => null],
            ['grc' => 'GRC-00000228', 'invoice_no' => 'INV-2026-8772', 'po' => '4500097611', 'company' => 'PBI', 'invoice_date' => today()->subDays(12), 'amount' => 745000.00, 'net' => 730100.00, 'match' => P2pThreeWayMatchStatus::Matched, 'status' => P2pStatus::Completed, 'days' => 0, 'sap_ref' => 'Clearing 1500004421', 'note' => null],
            ['grc' => 'GRC-00000222', 'invoice_no' => 'INV-2026-8711', 'po' => '4500097109', 'company' => 'PBI', 'invoice_date' => today()->subDays(14), 'amount' => 88000.00, 'net' => null, 'match' => P2pThreeWayMatchStatus::OpenPo, 'status' => P2pStatus::ReturnedToVendor, 'days' => 3, 'sap_ref' => null, 'note' => 'Reference number is missing on the invoice.'],
            ['grc' => 'GRC-00000219', 'invoice_no' => 'INV-2026-8690', 'po' => '4500096990', 'company' => 'OMMC', 'invoice_date' => today()->subDays(20), 'amount' => 64000.00, 'net' => null, 'match' => P2pThreeWayMatchStatus::NoMatch, 'status' => P2pStatus::Cancelled, 'days' => 0, 'sap_ref' => null, 'note' => null],
        ];
    }

    /**
     * Open escalations on my receipts, with the route and the current hop.
     *
     * @return list<array{number: string, reason: string, gr: string, company: string, route: list<string>, current: int, at: Carbon}>
     */
    public function escalations(): array
    {
        return [
            ['number' => 'ESC-000045', 'reason' => 'Quantity difference', 'gr' => '5000123410', 'company' => 'PBI', 'route' => ['You', 'Warehouse', 'You'], 'current' => 2, 'at' => now()->subDays(4)],
            ['number' => 'ESC-000042', 'reason' => 'Wrong unit of measure', 'gr' => '5000123389', 'company' => 'OMMC', 'route' => ['You', 'Procurement', 'You'], 'current' => 1, 'at' => now()->subDays(2)],
        ];
    }

    /**
     * @return array{resolved: int, avg_days: float}
     */
    public function escalationSummary(): array
    {
        return ['resolved' => 7, 'avg_days' => 3.2];
    }

    /**
     * Average days from invoice submission to payment, and per stage.
     *
     * @return array{total: float, change: float, stages: list<array{stage: string, days: float}>}
     */
    public function turnaround(): array
    {
        return [
            'total' => 13.0,
            'change' => -2.4,
            'stages' => [
                ['stage' => 'AP review', 'days' => 2.1],
                ['stage' => 'Accounting approval', 'days' => 1.4],
                ['stage' => 'Parking & posting in SAP', 'days' => 3.0],
                ['stage' => 'Payment', 'days' => 6.5],
            ],
        ];
    }

    /**
     * Amount submitted vs net paid per month, oldest first.
     *
     * @return list<array{month: string, billed: float, paid: float}>
     */
    public function billedVsPaid(): array
    {
        $values = [[2.6, 2.4], [3.1, 2.9], [3.5, 3.4], [2.8, 2.7], [3.2, 3.1], [3.9, 3.4]];

        return collect($values)->map(fn (array $pair, int $i): array => [
            'month' => now()->startOfMonth()->subMonths(5 - $i)->format('M'),
            'billed' => $pair[0] * 1_000_000,
            'paid' => $pair[1] * 1_000_000,
        ])->all();
    }

    /**
     * Down payments requested through RFP, per PO.
     *
     * @return list<array{po: string, description: string, amount: float, status: P2pDownPaymentStatus}>
     */
    public function downPayments(): array
    {
        return [
            ['po' => '4500097890', 'description' => 'Lead supply advance (20%)', 'amount' => 250000.00, 'status' => P2pDownPaymentStatus::Cleared],
            ['po' => '4500098200', 'description' => 'Battery moulds (30%)', 'amount' => 180000.00, 'status' => P2pDownPaymentStatus::Requested],
        ];
    }

    /**
     * @return list<array{text: string, tone: string, at: Carbon}>
     */
    public function activity(): array
    {
        return [
            ['text' => 'GRC-00000228 paid · clearing doc 1500004421', 'tone' => 'success', 'at' => now()->subHours(2)],
            ['text' => 'New GR 5000123502 synced from SAP', 'tone' => 'info', 'at' => today()->setTime(8, 0)],
            ['text' => 'GRC-00000231 returned by Ramcar AP', 'tone' => 'warning', 'at' => now()->subDay()],
            ['text' => 'ESC-000045 forwarded to Ramcar Warehouse', 'tone' => 'danger', 'at' => now()->subDays(2)],
        ];
    }
}
