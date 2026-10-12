<?php

namespace App\Filament\Resources\RfpApprovals\Widgets;

use App\Enums\RfpStatus;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\Rfp;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Legacy "RFPs For Approval" and "Total PHP Amount for This Month".
 */
class RfpApprovalStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $awaiting = Rfp::query()->awaiting(auth()->user());

        $thisMonth = RfpApprovalResource::getEloquentQuery()
            ->whereNotIn('status', [RfpStatus::Rejected, RfpStatus::Cancelled])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            Stat::make('RFPs for my action', number_format((clone $awaiting)->count()))
                ->description('PHP '.number_format((float) (clone $awaiting)->sum('total_php_amount'), 2))
                ->color('warning'),
            Stat::make('Total PHP amount this month', number_format((float) $thisMonth->sum('total_php_amount'), 2))
                ->description(number_format($thisMonth->count()).' RFPs created in '.now()->format('F')),
        ];
    }
}
