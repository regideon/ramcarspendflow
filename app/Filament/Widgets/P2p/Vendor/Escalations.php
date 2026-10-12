<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class Escalations extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.escalations';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 5];

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
