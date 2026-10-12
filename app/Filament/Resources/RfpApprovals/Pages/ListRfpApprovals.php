<?php

namespace App\Filament\Resources\RfpApprovals\Pages;

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\RfpApprovals\Widgets\RfpApprovalStats;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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
     * Legacy tabs: "For my action" for everyone; Accounting also gets one tab per queue
     * (legacy "For Processing - AP / AS / AM").
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $user = auth()->user();
        $count = fn (callable $scope): int => $scope(RfpApprovalResource::getEloquentQuery())->count();

        $tabs = [
            'mine' => Tab::make('For my action')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaiting($user))
                ->badge($count(fn (Builder $query) => $query->awaiting($user)))
                ->badgeColor('warning'),
            'all' => Tab::make('All'),
        ];

        if ($user->hasAnyRole([RoleName::Admin, ...RoleName::rfpAccounting()])) {
            $tabs += [
                'for_ap_review' => $this->statusTab('For AP Review', RfpStatus::ForApReview),
                'for_approval' => $this->statusTab('For Approval', RfpStatus::ForApproval),
                'for_ap_processing' => $this->statusTab('For Processing - AP', RfpStatus::ForApProcessing),
                'with_ap_supervisor' => $this->statusTab('For Processing - AS', RfpStatus::ForAccountingApproval, RoleName::ApSupervisor),
                'with_ap_manager' => $this->statusTab('For Processing - AM', RfpStatus::ForAccountingApproval, RoleName::ApManager),
                'for_treasury' => $this->statusTab('For Treasury', RfpStatus::ForTreasury),
            ];
        } else {
            $tabs['in_progress'] = Tab::make('In progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', [RfpStatus::ForRevision, ...RfpStatus::final()]));
        }

        return $tabs + [
            'for_revision' => $this->statusTab('For Revision', RfpStatus::ForRevision),
            'completed' => $this->statusTab('Completed', RfpStatus::Completed),
            'rejected' => $this->statusTab('Rejected', RfpStatus::Rejected),
            'cancelled' => $this->statusTab('Cancelled', RfpStatus::Cancelled),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'mine';
    }

    protected function statusTab(string $label, RfpStatus $status, ?RoleName $role = null): Tab
    {
        $scope = fn (Builder $query): Builder => $query
            ->where('status', $status)
            ->when($role, fn (Builder $query) => $query->where('current_approver_role', $role));

        return Tab::make($label)
            ->modifyQueryUsing($scope)
            ->badge(fn (): int => $scope(RfpApprovalResource::getEloquentQuery())->count());
    }
}
