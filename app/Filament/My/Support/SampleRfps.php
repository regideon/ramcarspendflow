<?php

namespace App\Filament\My\Support;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Placeholder RFPs for the My panel while its design is being signed off. One source
 * so the dashboard, My requests and RFP detail pages agree with each other.
 *
 * Replace with real queries once the pages are approved.
 */
class SampleRfps
{
    /**
     * The signed-in user's own requests.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function mine(): Collection
    {
        return collect([
            [
                'number' => 'RFP-2026-001399',
                'purpose' => 'Office supplies – October',
                'payee' => 'National Book Store',
                'amount' => 18250.00,
                'status' => RfpStatus::ForApproval,
                'date' => Carbon::parse('2026-10-24'),
                'dueDate' => Carbon::parse('2026-11-05'),
                'note' => 'With Department Head · 1 day',
                'icon' => Heroicon::OutlinedArchiveBox,
                'tone' => 'sky',
                'items' => [
                    ['description' => 'High-yield toner cartridges (Black & Cyan)', 'invoice' => 'NBS-88910', 'invoiceDate' => '2026-10-22', 'subcategory' => 'Printing & Stationery', 'po' => 'PO-2026-0941', 'amount' => 11400.00],
                    ['description' => 'A4 Copy Paper 80gsm (20 reams)', 'invoice' => 'NBS-88910', 'invoiceDate' => '2026-10-22', 'subcategory' => 'Printing & Stationery', 'po' => 'PO-2026-0941', 'amount' => 4850.00],
                    ['description' => 'Heavy duty desktop staplers & clips', 'invoice' => 'NBS-88912', 'invoiceDate' => '2026-10-23', 'subcategory' => 'Office Supplies', 'po' => null, 'amount' => 2000.00],
                ],
                'attachments' => [
                    ['name' => 'Official_Receipt.pdf', 'size' => '1.4 MB', 'kind' => 'pdf', 'label' => 'Scanned invoice'],
                    ['name' => 'Quotation_Signed.jpg', 'size' => '840 KB', 'kind' => 'image', 'label' => 'Vendor quote'],
                    ['name' => 'Delivery_Receipt.pdf', 'size' => '620 KB', 'kind' => 'pdf', 'label' => 'Receiving copy'],
                ],
            ],
            [
                'number' => 'RFP-2026-001382',
                'purpose' => 'Q4 Team Strategic Planning Venue',
                'payee' => 'Discovery Suites',
                'amount' => 95000.00,
                'status' => RfpStatus::ForRevision,
                'date' => Carbon::parse('2026-10-22'),
                'note' => 'AP Review: Please attach the signed quotation.',
                'icon' => Heroicon::OutlinedCalendarDays,
                'tone' => 'orange',
            ],
            [
                'number' => 'RFP-2026-001511',
                'purpose' => 'Client dinner – Makati',
                'type' => RfpRequestType::Reimbursement,
                'amount' => 6480.00,
                'status' => RfpStatus::Draft,
                'date' => Carbon::parse('2026-10-28'),
                'note' => 'Last edited 3h ago',
                'icon' => Heroicon::OutlinedBanknotes,
                'tone' => 'violet',
            ],
            [
                'number' => 'RFP-2026-001360',
                'purpose' => 'Fleet GPS subscription',
                'payee' => 'Cartrack PH',
                'amount' => 54600.00,
                'status' => RfpStatus::ForApProcessing,
                'date' => Carbon::parse('2026-10-19'),
                'note' => 'With AP Processor · 4 hours',
                'icon' => Heroicon::OutlinedMap,
                'tone' => 'slate',
            ],
            [
                'number' => 'RFP-2026-001314',
                'purpose' => 'Warehouse forklift repair',
                'payee' => 'Toyota Material Handling',
                'amount' => 128900.00,
                'status' => RfpStatus::ForTreasury,
                'date' => Carbon::parse('2026-10-14'),
                'note' => 'Payment release pending',
                'icon' => Heroicon::OutlinedWrenchScrewdriver,
                'tone' => 'amber',
            ],
            [
                'number' => 'RFP-2026-001290',
                'purpose' => 'Annual AWS Cloud Reserved Instances',
                'payee' => 'Amazon Web Services',
                'amount' => 680000.00,
                'status' => RfpStatus::Completed,
                'date' => Carbon::parse('2026-09-18'),
                'paidAt' => Carbon::parse('2026-10-02'),
                'note' => 'Ref 0045821',
                'icon' => Heroicon::OutlinedCloud,
                'tone' => 'emerald',
            ],
            [
                'number' => 'RFP-2026-001188',
                'purpose' => 'Team building transport',
                'payee' => 'Victory Liner Charter',
                'amount' => 22000.00,
                'status' => RfpStatus::Rejected,
                'date' => Carbon::parse('2026-09-05'),
                'note' => 'Rejected by Division Head – Budget not approved for Q3',
                'icon' => Heroicon::OutlinedArrowsRightLeft,
                'tone' => 'rose',
            ],
        ])->map(self::normalize(...));
    }

    /**
     * Other people's requests waiting on the signed-in matrix approver.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function awaitingMyApproval(): Collection
    {
        return collect([
            [
                'number' => 'RFP-2026-001482',
                'purpose' => 'Electricity – Pasig plant, October',
                'payee' => 'Meralco',
                'tag' => 'Utility',
                'requestor' => 'Carlo Reyes',
                'department' => 'Facilities',
                'amount' => 245000.00,
                'date' => Carbon::parse('2026-10-21'),
                'dueDate' => today(),
                'due' => 'Due today',
                'dueTone' => 'amber',
                'approver' => 2,
                'approvers' => 4,
                'icon' => Heroicon::OutlinedBolt,
                'tone' => 'amber',
            ],
            [
                'number' => 'RFP-2026-001490',
                'purpose' => 'Client visits – Cebu trip',
                'type' => RfpRequestType::Reimbursement,
                'payee' => 'Juan Dela Cruz',
                'tag' => 'Reimbursement',
                'requestor' => 'Juan Dela Cruz',
                'department' => 'Sales',
                'amount' => 38450.00,
                'date' => Carbon::parse('2026-10-16'),
                'dueDate' => today()->subDays(2),
                'due' => 'Overdue 2d',
                'dueTone' => 'rose',
                'approver' => 3,
                'approvers' => 4,
                'icon' => Heroicon::OutlinedReceiptPercent,
                'tone' => 'rose',
            ],
            [
                'number' => 'RFP-2026-001475',
                'purpose' => 'Fiber internet – head office, Q4',
                'payee' => 'PLDT Enterprise',
                'tag' => 'Telecom',
                'requestor' => 'Aileen Tan',
                'department' => 'IT Infra',
                'amount' => 412800.00,
                'date' => Carbon::parse('2026-10-20'),
                'dueDate' => Carbon::parse('2026-11-03'),
                'due' => 'Due Nov 3',
                'dueTone' => 'slate',
                'approver' => 2,
                'approvers' => 4,
                'icon' => Heroicon::OutlinedWifi,
                'tone' => 'indigo',
            ],
        ])->map(fn (array $rfp): array => self::normalize([...$rfp, 'status' => RfpStatus::ForApproval, 'awaitingMe' => true, 'myStep' => $rfp['approver']]));
    }

    /**
     * Other people's requests the signed-in matrix approver has already signed, returned
     * or rejected. `status` is where the RFP is now.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function decidedByMe(): Collection
    {
        return collect([
            [
                'number' => 'RFP-2026-001468',
                'purpose' => 'Fleet fuel – October',
                'payee' => 'Petron Corporation',
                'requestor' => 'Mark Villanueva',
                'department' => 'Logistics',
                'amount' => 86400.00,
                'status' => RfpStatus::ForApProcessing,
                'date' => Carbon::parse('2026-10-20'),
                'decision' => 'approved',
                'decidedAt' => Carbon::parse('2026-10-23'),
                'note' => 'With AP Processor · 2 days',
                'tone' => 'sky',
            ],
            [
                'number' => 'RFP-2026-001455',
                'purpose' => 'Security services – October',
                'payee' => 'Guardian Security Agency',
                'requestor' => 'Rhea Santos',
                'department' => 'Admin',
                'amount' => 132000.00,
                'status' => RfpStatus::ForTreasury,
                'date' => Carbon::parse('2026-10-15'),
                'decision' => 'approved',
                'decidedAt' => Carbon::parse('2026-10-20'),
                'note' => 'Payment release pending',
                'tone' => 'indigo',
            ],
            [
                'number' => 'RFP-2026-001449',
                'purpose' => 'Marketing collaterals – Q4 launch',
                'payee' => 'PrintPro Manila',
                'requestor' => 'Joy Mendoza',
                'department' => 'Marketing',
                'amount' => 57300.00,
                'status' => RfpStatus::ForRevision,
                'date' => Carbon::parse('2026-10-13'),
                'decision' => 'returned',
                'decidedAt' => Carbon::parse('2026-10-17'),
                'remarks' => 'The quotation is unsigned. Please attach the signed copy.',
                'note' => 'Department Head: The quotation is unsigned. Please attach the signed copy.',
                'tone' => 'orange',
            ],
            [
                'number' => 'RFP-2026-001431',
                'purpose' => 'Seminar fees – tax updates',
                'type' => RfpRequestType::Reimbursement,
                'payee' => 'Liza Gomez',
                'requestor' => 'Liza Gomez',
                'department' => 'Finance',
                'amount' => 12500.00,
                'status' => RfpStatus::Completed,
                'date' => Carbon::parse('2026-10-05'),
                'decision' => 'approved',
                'decidedAt' => Carbon::parse('2026-10-09'),
                'paidAt' => Carbon::parse('2026-10-20'),
                'note' => 'Ref 0046110',
                'tone' => 'emerald',
            ],
            [
                'number' => 'RFP-2026-001402',
                'purpose' => 'Office chairs replacement',
                'payee' => 'ErgoWorks Furniture',
                'requestor' => 'Carlo Reyes',
                'department' => 'Facilities',
                'amount' => 148000.00,
                'status' => RfpStatus::Rejected,
                'date' => Carbon::parse('2026-09-22'),
                'decision' => 'rejected',
                'decidedAt' => Carbon::parse('2026-09-26'),
                'remarks' => 'Not in this year\'s capex plan.',
                'note' => 'Rejected by Department Head – Not in this year\'s capex plan.',
                'approvers' => 2,
                'tone' => 'rose',
            ],
        ])->map(fn (array $rfp): array => self::normalize(['myStep' => 2, 'remarks' => null, ...$rfp]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $number): ?array
    {
        return self::mine()->concat(self::awaitingMyApproval())->concat(self::decidedByMe())->firstWhere('number', $number);
    }

    /**
     * Fill in what a row leaves out, so pages can rely on every key being there.
     *
     * @param  array<string, mixed>  $rfp
     * @return array<string, mixed>
     */
    protected static function normalize(array $rfp): array
    {
        $rfp = [
            'type' => RfpRequestType::Standard,
            'payee' => null,
            'requestor' => null,
            'department' => null,
            'paidAt' => null,
            'note' => null,
            'awaitingMe' => false,
            'myStep' => null,
            'approver' => 2,
            'approvers' => 3,
            ...$rfp,
        ];

        $rfp['dueDate'] ??= $rfp['date']->copy()->addWeekdays(10);
        $rfp['items'] ??= [['description' => $rfp['purpose'], 'invoice' => 'SI-'.substr($rfp['number'], -4), 'invoiceDate' => $rfp['date']->toDateString(), 'subcategory' => 'General Services', 'po' => null, 'amount' => $rfp['amount']]];
        $rfp['attachments'] ??= [['name' => 'Official_Receipt.pdf', 'size' => '1.1 MB', 'kind' => 'pdf', 'label' => 'Scanned invoice']];

        return $rfp;
    }
}
