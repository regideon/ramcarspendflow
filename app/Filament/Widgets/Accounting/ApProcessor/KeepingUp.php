<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\ChartWidget;

class KeepingUp extends ChartWidget
{
    use InteractsWithApProcessorDashboard;

    protected static ?int $sort = 5;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Keeping up';

    protected ?string $maxHeight = '240px';

    public function getDescription(): ?string
    {
        $days = collect($this->dashboard()->throughput());
        $backlog = $days->sum('received') - $days->sum('completed');

        return sprintf('Received vs completed per day, last 14 days · backlog %+d', $backlog);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = collect($this->dashboard()->throughput());

        return [
            'datasets' => [
                [
                    'label' => 'Received into my queue',
                    'data' => $days->pluck('received')->all(),
                    'backgroundColor' => '#cbd5e1',
                    'borderRadius' => 3,
                ],
                [
                    'label' => 'Completed by me',
                    'data' => $days->pluck('completed')->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 3,
                ],
            ],
            'labels' => $days->pluck('day')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
