<section data-tour="escalations" class="sf-card p-6">
    <x-sf.card-header heading="Escalations raised" description="P2P invoices you escalated that are still open">
        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium tabular-nums text-gray-600 dark:bg-white/10 dark:text-gray-300">Avg {{ $escalationSummary['avg_days'] }}d to resolve</span>
    </x-sf.card-header>

    <div class="mt-2 divide-y divide-gray-100 dark:divide-white/5">
        @forelse ($escalations as $escalation)
            <div class="flex items-start justify-between gap-3 py-3">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-x-2">
                        <span class="font-mono text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $escalation['number'] }}</span>
                        <span class="text-xs font-semibold text-gray-950 dark:text-white">{{ $escalation['reason'] }}</span>
                    </div>
                    <p class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $escalation['grc'] }} · raised {{ $escalation['at']->diffForHumans() }}</p>
                    <div class="flex flex-wrap items-center gap-1 text-[0.625rem]">
                        @foreach ($escalation['route'] as $i => $stop)
                            @if (! $loop->first)
                                <x-filament::icon icon="heroicon-m-chevron-right" class="size-3 text-gray-300 dark:text-gray-600" />
                            @endif
                            <span @class([
                                'rounded px-1.5 py-0.5',
                                'bg-primary-50 font-semibold text-primary-700 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30' => $i === $escalation['current'],
                                'text-gray-400' => $i !== $escalation['current'],
                            ])>{{ $stop }}</span>
                        @endforeach
                    </div>
                </div>
                <span class="shrink-0 rounded-full bg-warning-50 px-2.5 py-1 text-[0.6875rem] font-semibold text-warning-700 ring-1 ring-warning-200/60 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/30">
                    With {{ $escalation['route'][$escalation['current']] }}
                </span>
            </div>
        @empty
            <p class="py-8 text-center text-sm text-gray-400">No open escalations.</p>
        @endforelse
    </div>
</section>
