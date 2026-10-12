<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\Widget;

class RequestsInFlight extends Widget
{
    use InteractsWithRequestorDashboard;

    protected string $view = 'filament.widgets.rfp.requestor.requests-in-flight';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['rows' => $this->dashboard()->requestsInFlight()];
    }
}
