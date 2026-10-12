<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\Widget;

class ActionNeeded extends Widget
{
    use InteractsWithRequestorDashboard;

    protected string $view = 'filament.widgets.rfp.requestor.action-needed';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => $this->dashboard()->actionNeeded()];
    }
}
