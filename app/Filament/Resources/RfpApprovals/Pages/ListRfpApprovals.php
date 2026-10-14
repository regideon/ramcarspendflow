<?php

namespace App\Filament\Resources\RfpApprovals\Pages;

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\RfpApprovals\Widgets\RfpApprovalStats;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListRfpApprovals extends ListRecords
{
    protected static string $resource = RfpApprovalResource::class;

    protected static ?string $title = 'RFP Approvals';

    protected function getHeaderWidgets(): array
    {
        return [
            RfpApprovalStats::class,
        ];
    }

    /**
     * Tabs follow the RFP route: Requestor -> AP Processor -> Matrix approvers -> AP Processor
     * -> AP Supervisor, AP Manager -> Treasury -> Payment released. "For my action" comes first,
     * then one tab per stage (Accounting only), then the off-route outcomes, then "All".
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $user = auth()->user();
        $count = fn (callable $scope): int => $scope(RfpApprovalResource::getEloquentQuery())->count();

        $tabs = [
            'mine' => Tab::make('For my action')
                ->icon(Heroicon::OutlinedBellAlert)
                ->modifyQueryUsing(fn (Builder $query) => $query->awaiting($user))
                ->badge($count(fn (Builder $query) => $query->awaiting($user)))
                ->badgeColor('warning'),
        ];

        if ($user->hasAnyRole([RoleName::Admin, ...RoleName::rfpAccounting()])) {
            $tabs += [
                'for_ap_review' => $this->statusTab('1. AP Processor', RfpStatus::ForApReview, icon: Heroicon::OutlinedDocumentMagnifyingGlass),
                'for_approval' => $this->statusTab('2. Matrix Approval', RfpStatus::ForApproval, icon: Heroicon::OutlinedUserGroup),
                'for_ap_processing' => $this->statusTab('3. AP Processor', RfpStatus::ForApProcessing, icon: Heroicon::OutlinedCalculator),
                'with_ap_supervisor' => $this->statusTab('4. AP Supervisor', RfpStatus::ForAccountingApproval, RoleName::ApSupervisor, Heroicon::OutlinedShieldCheck),
                'with_ap_manager' => $this->statusTab('5. AP Manager', RfpStatus::ForAccountingApproval, RoleName::ApManager, Heroicon::OutlinedShieldCheck),
                'for_treasury' => $this->statusTab('6. Treasury', RfpStatus::ForTreasury, icon: Heroicon::OutlinedBanknotes),
            ];
        } else {
            $tabs['in_progress'] = Tab::make('In progress')
                ->icon(Heroicon::OutlinedArrowPath)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', [RfpStatus::ForRevision, ...RfpStatus::final()]));
        }

        return $tabs + [
            'completed' => $this->statusTab('Payment Released', RfpStatus::Completed, icon: Heroicon::OutlinedCheckBadge),
            'for_revision' => $this->statusTab('Returned to Requestor', RfpStatus::ForRevision, icon: Heroicon::OutlinedArrowUturnLeft),
            'rejected' => $this->statusTab('Rejected', RfpStatus::Rejected, icon: Heroicon::OutlinedXCircle),
            'cancelled' => $this->statusTab('Cancelled', RfpStatus::Cancelled, icon: Heroicon::OutlinedNoSymbol),
            'all' => Tab::make('All')->icon(Heroicon::OutlinedQueueList),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'mine';
    }

    protected function statusTab(string $label, RfpStatus $status, ?RoleName $role = null, ?Heroicon $icon = null): Tab
    {
        $scope = fn (Builder $query): Builder => $query
            ->where('status', $status)
            ->when($role, fn (Builder $query) => $query->where('current_approver_role', $role));

        return Tab::make($label)
            ->icon($icon)
            ->modifyQueryUsing($scope)
            ->badge(fn (): int => $scope(RfpApprovalResource::getEloquentQuery())->count());
    }
}
