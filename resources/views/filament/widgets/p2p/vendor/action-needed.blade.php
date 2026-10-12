@php
    use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
    use App\Filament\Resources\P2p\Invoices\InvoiceResource;
    use App\Support\Peso;

    $returned = $items['returned'];
    $ready = $items['ready'];
    $escalations = $items['escalations'];

    $summary = collect([
        count($returned) ? count($returned).' '.str('invoice')->plural(count($returned)).' returned to you' : null,
        $ready['count'] ? $ready['count'].' goods receipts ready to bill' : null,
        count($escalations) ? count($escalations).' '.str('escalation')->plural(count($escalations)).' waiting on you' : null,
    ])->filter()->implode(' · ');
@endphp

<x-filament-widgets::widget>
    @if ($summary !== '')
        <div class="overflow-hidden rounded-xl border-s-4 border-warning-500 bg-warning-50 p-4 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/20">
            <div class="mb-3 flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Action needed · {{ $summary }}</h2>
            </div>

            <div class="flex flex-col gap-2">
                @foreach ($returned as $item)
                    <div class="flex flex-col gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 md:flex-row md:items-center dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 md:w-[26rem] md:shrink-0">
                            <x-filament::badge color="warning">Returned to you</x-filament::badge>
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $item['grc'] }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">PO {{ $item['po'] }}</span>
                            <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($item['amount']) }}</span>
                        </div>
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-md bg-danger-50 px-3 py-1.5 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                            <x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" class="h-4 w-4 shrink-0" />
                            <span class="truncate italic">"{{ $item['remark'] }}"</span>
                            <span class="ms-auto whitespace-nowrap text-xs text-gray-500">Returned {{ $item['at']->diffForHumans() }}</span>
                        </div>
                        <x-filament::button tag="a" :href="InvoiceResource::getUrl('index')" size="sm" color="warning" icon="heroicon-m-pencil-square" icon-position="after" class="shrink-0">
                            Fix &amp; resubmit
                        </x-filament::button>
                    </div>
                @endforeach

                @if ($ready['count'])
                    <div class="flex flex-col gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 md:flex-row md:items-center dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::badge color="gray" class="w-fit">Ready to bill</x-filament::badge>
                        <p class="flex-1 text-sm text-gray-700 dark:text-gray-300">
                            <strong class="font-semibold text-gray-950 dark:text-white">{{ $ready['count'] }} goods receipts</strong> are ready to bill
                            @if ($ready['due_soon'])
                                · <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $ready['due_soon'] }} due within 3 days</span>
                            @endif
                        </p>
                        <x-filament::button tag="a" :href="GoodsReceiptResource::getUrl('index')" size="sm" color="gray" icon="heroicon-m-arrow-right" icon-position="after" class="shrink-0">
                            Bill now
                        </x-filament::button>
                    </div>
                @endif

                @foreach ($escalations as $escalation)
                    <div class="flex flex-col gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 md:flex-row md:items-center dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::badge color="danger" class="w-fit">Escalation</x-filament::badge>
                        <p class="flex-1 text-sm text-gray-700 dark:text-gray-300">
                            <strong class="font-semibold text-gray-950 dark:text-white">{{ $escalation['number'] }}</strong>
                            ({{ $escalation['reason'] }}, GR {{ $escalation['gr'] }}, {{ $escalation['company'] }}) is back with you for a reply.
                        </p>
                        <x-filament::button tag="a" href="#vendor-escalations" size="sm" color="gray" icon="heroicon-m-arrow-down" icon-position="after" class="shrink-0">
                            View
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
