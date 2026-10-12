<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use App\Support\Peso;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RequestorStats extends StatsOverviewWidget
{
    use InteractsWithRequestorDashboard;

    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int|array|null
    {
        return 4;
    }

    protected function getStats(): array
    {
        $stats = $this->dashboard()->stats();
        $faster = $stats['avg_days_change'] <= 0;

        return [
            Stat::make('Needs my action', $stats['revision'] + $stats['drafts'])
                ->description("{$stats['revision']} for revision · {$stats['drafts']} drafts")
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
            Stat::make('In progress', $stats['in_progress'].' requests')
                ->description(Peso::format($stats['in_progress_amount']).' with approvers, Accounting and Treasury')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('info'),
            Stat::make('Paid this month', Peso::format($stats['paid_amount'], compact: true))
                ->description("{$stats['paid_count']} requests · ".sprintf('%+d%%', $stats['paid_change']).' vs last month')
                ->descriptionIcon($stats['paid_change'] >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->chart([4, 6, 5, 8, 7, 10, 12])
                ->color('success'),
            Stat::make('Avg. time to payment', $stats['avg_days'].' days')
                ->description(sprintf('%+.1fd vs last quarter', $stats['avg_days_change']))
                ->descriptionIcon($faster ? Heroicon::ArrowTrendingDown : Heroicon::ArrowTrendingUp)
                ->color($faster ? 'success' : 'danger'),
        ];
    }
}
