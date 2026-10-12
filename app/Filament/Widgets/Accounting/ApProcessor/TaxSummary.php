<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\Widget;

class TaxSummary extends Widget
{
    use InteractsWithApProcessorDashboard;

    protected string $view = 'filament.widgets.accounting.ap-processor.tax-summary';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 7;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['tax' => $this->dashboard()->taxSummary()];
    }
}
