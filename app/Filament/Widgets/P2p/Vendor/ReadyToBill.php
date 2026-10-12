<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class ReadyToBill extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.ready-to-bill';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 6];

    protected static ?int $sort = 3;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['receipts' => $this->dashboard()->readyToBill()];
    }
}
