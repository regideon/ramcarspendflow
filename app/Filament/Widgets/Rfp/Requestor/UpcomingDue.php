<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\Widget;

class UpcomingDue extends Widget
{
    use InteractsWithRequestorDashboard;

    protected string $view = 'filament.widgets.rfp.requestor.upcoming-due';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 7;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => $this->dashboard()->upcomingDue()];
    }
}
