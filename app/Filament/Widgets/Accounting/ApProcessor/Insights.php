<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\Widget;

class Insights extends Widget
{
    use InteractsWithApProcessorDashboard;

    protected string $view = 'filament.widgets.accounting.ap-processor.insights';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 8;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $dashboard = $this->dashboard();

        return [
            'decisions' => $dashboard->decisions(),
            'returnRate' => $dashboard->supervisorReturnRate(),
            'reasons' => $dashboard->returnReasons(),
            'activity' => $dashboard->activity(),
        ];
    }
}
