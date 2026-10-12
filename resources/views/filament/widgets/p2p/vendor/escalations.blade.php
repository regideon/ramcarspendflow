<x-filament-widgets::widget class="h-full" id="vendor-escalations">
    <x-filament::section heading="Escalations" description="Receipts Ramcar is correcting in SAP" class="h-full">
        <x-slot name="afterHeader">
            <x-filament::badge color="danger">{{ count($escalations) }} open</x-filament::badge>
        </x-slot>

        <div class="flex flex-col gap-3">
            @forelse ($escalations as $escalation)
                @php($withYou = $escalation['route'][$escalation['current']] === 'You')
                <div class="flex flex-col gap-2 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $escalation['number'] }}</span>
                        <x-filament::badge :color="$withYou ? 'warning' : 'gray'" size="sm">
                            {{ $withYou ? 'Waiting on you' : 'With Ramcar '.$escalation['route'][$escalation['current']] }}
                        </x-filament::badge>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <strong class="font-semibold text-gray-950 dark:text-white">{{ $escalation['reason'] }}</strong>
                        on GR {{ $escalation['gr'] }} ({{ $escalation['company'] }}) · opened {{ $escalation['at']->diffForHumans() }}
                    </p>
                    <ol class="flex flex-wrap items-center gap-1 text-xs" aria-label="Route">
                        @foreach ($escalation['route'] as $i => $hop)
                            @unless ($loop->first)
                                <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-4 w-4 text-gray-400" />
                            @endunless
                            <li @class([
                                'rounded px-1.5 py-0.5',
                                'bg-info-100 font-semibold text-info-800 dark:bg-info-500/20 dark:text-info-300' => $i === $escalation['current'],
                                'text-gray-500 dark:text-gray-400' => $i !== $escalation['current'],
                            ])>{{ $hop }}</li>
                        @endforeach
                    </ol>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No open escalations.</p>
            @endforelse
        </div>

        <x-slot name="footer">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Resolved this quarter: <strong class="text-gray-950 dark:text-white">{{ $summary['resolved'] }}</strong>
                · average <strong class="text-gray-950 dark:text-white">{{ number_format($summary['avg_days'], 1) }} days</strong>
            </p>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
