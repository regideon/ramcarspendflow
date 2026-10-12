<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\ChartWidget;

class SpendOverTime extends ChartWidget
{
    use InteractsWithRequestorDashboard;

    protected static ?int $sort = 6;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Spend over time';

    protected ?string $description = 'Submitted vs paid, last 6 months';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = collect($this->dashboard()->spendByMonth());

        return [
            'datasets' => [
                [
                    'label' => 'Submitted',
                    'data' => $months->pluck('submitted')->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Paid',
                    'data' => $months->pluck('paid')->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $months->pluck('month')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
