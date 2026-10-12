<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use Filament\Widgets\Widget;

class QueueAging extends Widget
{
    use InteractsWithApProcessorDashboard;

    protected string $view = 'filament.widgets.accounting.ap-processor.queue-aging';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['buckets' => $this->dashboard()->aging()];
    }
}
