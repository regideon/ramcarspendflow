<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class Insights extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.insights';

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
            'months' => $dashboard->billedVsPaid(),
            'downPayments' => $dashboard->downPayments(),
            'activity' => $dashboard->activity(),
        ];
    }
}
