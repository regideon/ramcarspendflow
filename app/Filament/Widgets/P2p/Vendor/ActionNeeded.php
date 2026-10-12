<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class ActionNeeded extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.action-needed';

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
