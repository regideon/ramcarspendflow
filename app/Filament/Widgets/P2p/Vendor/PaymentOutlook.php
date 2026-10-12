<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class PaymentOutlook extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.payment-outlook';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 4];

    protected static ?int $sort = 4;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['buckets' => $this->dashboard()->paymentOutlook()];
    }
}
