<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\Widget;

class StageWait extends Widget
{
    use InteractsWithRequestorDashboard;

    protected string $view = 'filament.widgets.rfp.requestor.stage-wait';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['stages' => $this->dashboard()->stageWait()];
    }
}
