<?php

namespace App\Filament\Widgets\P2p\Vendor;

use App\Support\Peso;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VendorStats extends StatsOverviewWidget
{
    use InteractsWithVendorDashboard;

    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int|array|null
    {
        return ['md' => 3, 'xl' => 5];
    }

    protected function getStats(): array
    {
        $stats = $this->dashboard()->stats();

        return [
            Stat::make('Ready to bill', $stats['ready_grs'].' GRs')
                ->description("{$stats['ready_lines']} lines · {$stats['due_soon']} due soon")
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('warning'),
            Stat::make('In review at Ramcar', Peso::format($stats['review_amount'], compact: true))
                ->description("{$stats['review_count']} invoices")
                ->descriptionIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color('info'),
            Stat::make('On hold (escalated)', Peso::format($stats['hold_amount'], compact: true))
                ->description("{$stats['hold_count']} invoices being corrected by Ramcar")
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),
            Stat::make('Approved, awaiting payment', Peso::format($stats['approved_amount'], compact: true))
                ->description("{$stats['approved_count']} invoices parked or posted in SAP")
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color('primary'),
            Stat::make('Paid this month', Peso::format($stats['paid_net'], compact: true))
                ->description("{$stats['paid_count']} invoices, net of EWT · ".sprintf('%+d%%', $stats['paid_change']).' vs last month')
                ->descriptionIcon($stats['paid_change'] >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->chart([5, 7, 6, 8, 9, 11, 12])
                ->color('success'),
        ];
    }
}
