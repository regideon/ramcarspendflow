<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\Widget;

class BackOnDesk extends Widget
{
    use InteractsWithApProcessorDashboard;

    protected string $view = 'filament.widgets.accounting.ap-processor.back-on-desk';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => $this->dashboard()->backOnDesk()];
    }
}
