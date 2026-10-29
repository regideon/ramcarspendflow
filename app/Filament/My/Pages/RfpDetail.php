<?php

namespace App\Filament\My\Pages;

use App\Enums\RfpStatus;
use App\Filament\My\Support\SampleRfps;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;

/**
 * One RFP, for its requestor, for the matrix approver whose turn it is, or for an
 * approver who already decided on it. The approver whose turn it is also gets
 * Approve / Return / Reject.
 *
 * Content comes from SampleRfps until the design is signed off; decisions are not saved.
 */
class RfpDetail extends Page
{
    protected static string $layout = 'filament.my.layout';

    protected string $view = 'filament.my.pages.rfp-detail';

    protected static ?string $slug = 'rfps/{number}';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Matrix roles in the order they sign, for the sample approval chain.
     *
     * @var list<string>
     */
    public const array MATRIX_ROLES = ['Line Manager', 'Department Head', 'Division Head', 'BU Head'];

    public string $number;

    /**
     * Opens the decision sheet on arrival, e.g. from an Approve button on a list.
     */
    #[Url]
    public ?string $decide = null;

    /**
     * The decision just made, for the success screen: decision (approve, return, reject),
     * remarks and when.
     *
     * @var array{decision: string, remarks: ?string, at: string}|null
     */
    public ?array $outcome = null;

    public function mount(string $number): void
    {
        abort_unless(SampleRfps::find($number), 404);

        $this->number = $number;
    }

    public function getTitle(): string
    {
        return $this->number;
    }

    /**
     * @return array<string, mixed>
     */
    public function rfp(): array
    {
        return SampleRfps::find($this->number);
    }

    /**
     * The signed-in user is the approver whose turn it is (otherwise they are the requestor).
     */
    public function isApproverView(): bool
    {
        return $this->rfp()['awaitingMe'];
    }

    /**
     * The signed-in user raised this RFP (otherwise they are one of its approvers).
     */
    public function isMine(): bool
    {
        return $this->rfp()['requestor'] === null;
    }

    /**
     * The decision sheet to open on arrival, if the link asked for one.
     */
    public function initialSheet(): ?string
    {
        return $this->isApproverView() && in_array($this->decide, ['approve', 'return', 'reject'], true) ? $this->decide : null;
    }

    public function requestorName(): string
    {
        return $this->rfp()['requestor'] ?? auth()->user()->name;
    }

    /**
     * Line items with VAT (12%, inclusive) and EWT (1% of the net of VAT) worked out.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $rfp = $this->rfp();

        return collect($rfp['items'])->map(function (array $item) use ($rfp): array {
            $vat = round($item['amount'] / 1.12 * 0.12, 2);
            $ewt = round(($item['amount'] - $vat) * 0.01, 2);

            return [
                ...$item,
                'vendor' => $rfp['payee'] ?? $this->requestorName(),
                'invoiceDate' => Carbon::parse($item['invoiceDate']),
                'costCenter' => 'CC-104 Admin',
                'vat' => $vat,
                'ewt' => $ewt,
                'net' => $item['amount'] - $ewt,
            ];
        })->all();
    }

    /**
     * @return array{gross: float, vat: float, ewt: float, net: float}
     */
    public function totals(): array
    {
        $items = collect($this->items());

        return [
            'gross' => $items->sum('amount'),
            'vat' => $items->sum('vat'),
            'ewt' => $items->sum('ewt'),
            'net' => $items->sum('net'),
        ];
    }

    /**
     * The four stages, each done / current / returned / rejected / upcoming. The Approval
     * stage carries the matrix approvers as sub-steps.
     *
     * @return list<array{name: string, state: string, note: ?string, approvers: list<array{role: string, state: string, note: ?string, isMe: bool}>}>
     */
    public function timeline(): array
    {
        $rfp = $this->rfp();
        $status = $rfp['status'];
        $stage = MyRequests::stageOf($status);

        $stateOf = fn (int $position): string => match (true) {
            $status === RfpStatus::Completed => 'done',
            $status === RfpStatus::ForRevision => $position === 1 ? 'returned' : 'upcoming',
            in_array($status, [RfpStatus::Rejected, RfpStatus::Cancelled], true) => match (true) {
                $position < 2 => 'done',
                $position === 2 => 'rejected',
                default => 'upcoming',
            },
            $position < $stage => 'done',
            $position === $stage => 'current',
            default => 'upcoming',
        };

        $approverState = function (int $position) use ($stateOf, $rfp): string {
            return match ($stateOf(2)) {
                'done' => 'done',
                'current' => match (true) {
                    $position < $rfp['approver'] => 'done',
                    $position === $rfp['approver'] => 'current',
                    default => 'upcoming',
                },
                'rejected' => $position < $rfp['approvers'] ? 'done' : 'rejected',
                default => 'upcoming',
            };
        };

        $approvers = collect(array_slice(self::MATRIX_ROLES, 0, $rfp['approvers']))
            ->map(fn (string $role, int $index): array => [
                'role' => $role,
                'state' => $state = $approverState($index + 1),
                'note' => match ($state) {
                    'done' => 'Approved · Oct 25',
                    'current' => 'Waiting · 1 day',
                    'rejected' => $rfp['note'],
                    default => null,
                },
                'isMe' => $index + 1 === $rfp['myStep'],
            ])
            ->all();

        $notes = [
            1 => ['done' => 'Verified by J. Cruz · Oct 24', 'returned' => $rfp['note'], 'current' => 'With AP Review'],
            2 => ['done' => 'All approvers signed', 'current' => null, 'rejected' => 'Rejected'],
            3 => ['done' => 'Booked by AP · Oct 27', 'current' => 'With AP Processor'],
            4 => ['done' => $rfp['paidAt'] ? 'Paid '.$rfp['paidAt']->format('M j').' · '.$rfp['note'] : 'Payment released', 'current' => 'Payment release pending'],
        ];

        return collect(MyRequests::STAGES)
            ->map(fn (string $name, int $index): array => [
                'name' => $name,
                'state' => $state = $stateOf($index + 1),
                'note' => $status === RfpStatus::Draft ? null : ($notes[$index + 1][$state] ?? null),
                'approvers' => $index === 1 ? $approvers : [],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        $rfp = $this->rfp();

        return [
            'Company' => 'Ramcar Inc.',
            'Request type' => $rfp['type']->getLabel(),
            'Due type' => 'Standard · 5-day processing',
            'Account group' => '0001 · Vendor',
            'Payment method' => 'Check',
            'Pre-approved' => 'No',
            'Direct to VP' => 'No',
        ];
    }

    /**
     * @return list<array{author: string, role: string, body: string, at: string, mine: bool}>
     */
    public function messages(): array
    {
        return [
            ['author' => 'J. Cruz', 'role' => 'AP Review', 'body' => 'Verified against the official receipt. Routing to the approvers.', 'at' => 'Oct 24, 2:10 PM', 'mine' => false],
            ['author' => $this->requestorName(), 'role' => 'Requestor', 'body' => 'Thank you! These cover our department through November.', 'at' => 'Oct 24, 2:20 PM', 'mine' => $this->isMine()],
        ];
    }

    /**
     * Record the approver's decision. Preview only: nothing is saved yet.
     */
    public function submitDecision(string $decision, ?string $remarks = null): bool
    {
        abort_unless($this->isApproverView(), 403);

        Validator::make(
            ['decision' => $decision, 'remarks' => $remarks],
            [
                'decision' => ['required', 'in:approve,return,reject'],
                'remarks' => ['nullable', 'required_unless:decision,approve', 'string', 'max:1000'],
            ],
            ['remarks.required_unless' => 'Tell the requestor why.'],
        )->validate();

        $this->outcome = ['decision' => $decision, 'remarks' => filled($remarks) ? $remarks : null, 'at' => now()->format('M j, g:i A')];

        return true;
    }

    /**
     * Where the RFP goes after the approver's decision, for the success screen.
     *
     * @return array{step: int, steps: int, next: ?string, nextApproval: ?array<string, mixed>}
     */
    public function afterDecision(): array
    {
        $rfp = $this->rfp();
        $step = (int) $rfp['myStep'];

        return [
            'step' => $step,
            'steps' => $rfp['approvers'],
            'next' => match ($this->outcome['decision'] ?? null) {
                'approve' => self::MATRIX_ROLES[$step] ?? 'AP Processing',
                'return' => $this->requestorName(),
                default => null,
            },
            'nextApproval' => SampleRfps::awaitingMyApproval()
                ->reject(fn (array $other): bool => $other['number'] === $this->number)
                ->sortBy('dueDate')
                ->first(),
        ];
    }
}
