<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use Filament\Widgets\Widget;

class Pipeline extends Widget
{
    use InteractsWithRequestorDashboard;

    protected string $view = 'filament.widgets.rfp.requestor.pipeline';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['stages' => $this->dashboard()->pipeline()];
    }
}
