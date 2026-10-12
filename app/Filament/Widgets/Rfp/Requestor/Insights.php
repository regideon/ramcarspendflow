<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\Widget;

class Insights extends Widget
{
    use InteractsWithRequestorDashboard;

    protected string $view = 'filament.widgets.rfp.requestor.insights';

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
            'reasons' => $dashboard->returnReasons(),
            'categories' => $dashboard->categories(),
            'activity' => $dashboard->activity(),
        ];
    }
}
