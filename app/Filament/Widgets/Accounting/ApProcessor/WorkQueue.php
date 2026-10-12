<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\Widget;

class WorkQueue extends Widget
{
    use InteractsWithApProcessorDashboard;

    protected string $view = 'filament.widgets.accounting.ap-processor.work-queue';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $dashboard = $this->dashboard();

        return [
            'rows' => $dashboard->queue(),
            'total' => $dashboard->queueSize()['total'],
        ];
    }
}
