<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\Widget;

class EscalationsRaised extends Widget
{
    use InteractsWithApProcessorDashboard;

    protected string $view = 'filament.widgets.accounting.ap-processor.escalations-raised';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 6;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $dashboard = $this->dashboard();

        return [
            'escalations' => $dashboard->escalations(),
            'summary' => $dashboard->escalationSummary(),
        ];
    }
}
