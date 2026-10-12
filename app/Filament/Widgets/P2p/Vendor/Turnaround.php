<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class Turnaround extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.turnaround';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 5];

    protected static ?int $sort = 7;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['turnaround' => $this->dashboard()->turnaround()];
    }
}
