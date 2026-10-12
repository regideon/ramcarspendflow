<?php

namespace App\Filament\Resources\Rfps\Widgets;

use App\Enums\RfpStatus;
use App\Models\Rfp\Rfp;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Stepper across the top of an RFP: where it is on the route (docs/03-rfp-approval-flow.md).
 * Without a record (Create page) the request is at its first step.
 */
class RfpProgress extends Widget
{
    protected string $view = 'filament.rfps.widgets.rfp-progress';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public ?Model $record = null;

    /**
     * The route, one entry per stage; `statuses` are the RFP statuses that mean "at this stage".
     *
     * @var list<array{label: string, who: string, icon: Heroicon, statuses: list<RfpStatus>}>
     */
    protected function stages(): array
    {
        return [
            ['label' => 'New request', 'who' => 'Requestor', 'icon' => Heroicon::OutlinedPencilSquare, 'statuses' => [RfpStatus::Draft, RfpStatus::ForRevision]],
            ['label' => 'AP review', 'who' => 'AP Processor', 'icon' => Heroicon::OutlinedDocumentMagnifyingGlass, 'statuses' => [RfpStatus::ForApReview]],
            ['label' => 'Approval', 'who' => 'Matrix approvers', 'icon' => Heroicon::OutlinedUserGroup, 'statuses' => [RfpStatus::ForApproval]],
            ['label' => 'AP processing', 'who' => 'AP Processor', 'icon' => Heroicon::OutlinedCalculator, 'statuses' => [RfpStatus::ForApProcessing]],
            ['label' => 'Accounting approval', 'who' => 'AP Supervisor, AP Manager', 'icon' => Heroicon::OutlinedShieldCheck, 'statuses' => [RfpStatus::ForAccountingApproval]],
            ['label' => 'Treasury', 'who' => 'Treasury', 'icon' => Heroicon::OutlinedBanknotes, 'statuses' => [RfpStatus::ForTreasury]],
            ['label' => 'Completed', 'who' => 'Payment released', 'icon' => Heroicon::OutlinedCheckBadge, 'statuses' => [RfpStatus::Completed]],
        ];
    }

    /**
     * Each stage with its state: done, current, revision, failed, skipped or upcoming.
     *
     * @return list<array{label: string, who: string, icon: Heroicon, state: string, note: ?string}>
     */
    public function getSteps(): array
    {
        /** @var Rfp|null $rfp */
        $rfp = $this->record;
        $status = $rfp?->status ?? RfpStatus::Draft;
        $stages = $this->stages();

        $stoppedAt = in_array($status, [RfpStatus::Rejected, RfpStatus::Cancelled], true)
            ? $this->stageOf($rfp->histories()->where('to_status', $status)->value('from_status'))
            : null;

        $currentIndex = $stoppedAt ?? $this->stageOf($status) ?? 0;

        return collect($stages)
            ->map(function (array $stage, int $index) use ($status, $currentIndex, $stoppedAt, $rfp): array {
                $state = match (true) {
                    $status === RfpStatus::Completed => 'done',
                    $index === 2 && $rfp?->is_pre_approved && $currentIndex > 2 => 'skipped',
                    $index < $currentIndex => 'done',
                    $index > $currentIndex => 'upcoming',
                    $stoppedAt !== null => 'failed',
                    $status === RfpStatus::ForRevision => 'revision',
                    default => 'current',
                };

                $note = match ($state) {
                    'skipped' => 'Pre-approved',
                    'failed' => $status->getLabel(),
                    'revision' => 'For revision',
                    'current' => $rfp && $status->isAwaitingApproval() ? $rfp->currentApproverLabel() : null,
                    default => null,
                };

                return [
                    'label' => $stage['label'],
                    'who' => $stage['who'],
                    'icon' => match ($state) {
                        'done' => Heroicon::CheckCircle,
                        'failed' => Heroicon::XCircle,
                        'skipped' => Heroicon::OutlinedForward,
                        'revision' => Heroicon::OutlinedArrowUturnLeft,
                        default => $stage['icon'],
                    },
                    'state' => $state,
                    'note' => $note,
                ];
            })
            ->all();
    }

    protected function stageOf(?RfpStatus $status): ?int
    {
        foreach ($this->stages() as $index => $stage) {
            if (in_array($status, $stage['statuses'], true)) {
                return $index;
            }
        }

        return null;
    }
}
