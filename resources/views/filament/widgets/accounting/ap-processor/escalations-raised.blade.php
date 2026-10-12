<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Escalations I raised" description="P2P invoices on hold with Procurement or Warehouse" class="h-full">
        <x-slot name="afterHeader">
            <x-filament::badge color="info">{{ count($escalations) }} open</x-filament::badge>
        </x-slot>

        <div class="flex flex-col gap-3">
            @forelse ($escalations as $escalation)
                <div class="flex flex-col gap-2 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $escalation['number'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">on {{ $escalation['grc'] }}</span>
                            <x-filament::badge color="warning" size="sm">{{ $escalation['reason'] }}</x-filament::badge>
                        </div>
                        <span class="text-xs text-gray-500">{{ $escalation['at']->diffForHumans() }}</span>
                    </div>
                    <ol class="flex flex-wrap items-center gap-1 text-xs" aria-label="Route">
                        <li class="rounded bg-success-100 px-1.5 py-0.5 text-success-800 dark:bg-success-500/20 dark:text-success-300">You</li>
                        @foreach ($escalation['route'] as $i => $hop)
                            <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-4 w-4 text-gray-400" />
                            <li @class([
                                'rounded px-1.5 py-0.5',
                                'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' => $i < $escalation['current'],
                                'bg-info-500 font-semibold text-white' => $i === $escalation['current'],
                                'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $i > $escalation['current'],
                            ])>{{ $hop }}</li>
                        @endforeach
                    </ol>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No open escalations.</p>
            @endforelse
        </div>

        <x-slot name="footer">
            <p class="text-sm text-gray-500 dark:text-gray-400">Average resolution: <strong class="text-gray-950 dark:text-white">{{ number_format($summary['avg_days'], 1) }} days</strong></p>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
