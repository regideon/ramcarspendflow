<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use App\Support\Peso;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApProcessorStats extends StatsOverviewWidget
{
    use InteractsWithApProcessorDashboard;

    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int|array|null
    {
        return ['md' => 3, 'xl' => 6];
    }

    protected function getStats(): array
    {
        $stats = $this->dashboard()->stats();
        ['rfp_review' => $review, 'rfp_processing' => $processing, 'p2p' => $p2p, 'returned' => $returned, 'overdue' => $overdue, 'processed' => $processed] = $stats;

        return [
            Stat::make('RFP first review', $review['count'])
                ->description(Peso::format($review['amount'], compact: true)." · oldest {$review['oldest']}d")
                ->descriptionIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color('info'),
            Stat::make('RFP AP processing', $processing['count'])
                ->description(Peso::format($processing['amount'], compact: true)." · oldest {$processing['oldest']}d")
                ->descriptionIcon(Heroicon::OutlinedCalculator)
                ->color($processing['oldest'] > 5 ? 'warning' : 'info'),
            Stat::make('P2P to process', $p2p['count'])
                ->description(Peso::format($p2p['amount'], compact: true)." · {$p2p['no_match']} No Match")
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color($p2p['no_match'] > 0 ? 'danger' : 'info'),
            Stat::make('Returned to me', $returned['count'])
                ->description("{$returned['rfp']} RFP · {$returned['p2p']} P2P · ".Peso::format($returned['amount'], compact: true))
                ->descriptionIcon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning'),
            Stat::make('Overdue', $overdue['count'])
                ->description(Peso::format($overdue['amount'], compact: true).' past due date')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('danger'),
            Stat::make('Processed this week', $processed['count'])
                ->description(sprintf('%.1fd avg in my hands (%+.1fd)', $processed['avg_days'], $processed['avg_change']))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->chart([4, 6, 5, 7, 6, 8, 9])
                ->color('success'),
        ];
    }
}
