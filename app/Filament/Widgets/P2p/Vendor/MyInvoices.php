<?php

namespace App\Filament\Widgets\P2p\Vendor;

use Filament\Widgets\Widget;

class MyInvoices extends Widget
{
    use InteractsWithVendorDashboard;

    protected string $view = 'filament.widgets.p2p.vendor.my-invoices';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['invoices' => $this->dashboard()->invoices()];
    }
}
