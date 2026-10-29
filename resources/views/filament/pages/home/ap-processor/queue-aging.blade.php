@php
    $total = collect($aging)->sum(fn (array $bucket): int => $bucket['rfp'] + $bucket['p2p']) ?: 1;

    // Youngest to oldest: green, amber, red, deep red.
    $bars = ['bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-danger-700'];
    $counts = ['text-gray-950 dark:text-white', 'text-warning-700 dark:text-warning-400', 'text-danger-600 dark:text-danger-400', 'text-danger-700 dark:text-danger-400'];

    $olderThanFive = collect($aging)->slice(2)->sum(fn (array $bucket): int => $bucket['rfp'] + $bucket['p2p']);
@endphp

<section data-tour="queue-aging" class="sf-card flex flex-col justify-between p-6">
    <div>
        <x-sf.card-header heading="Queue aging" description="How long items have been sitting with you">
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $total }} items</span>
        </x-sf.card-header>

        <div class="mt-4 space-y-3.5">
            @foreach ($aging as $bucket)
                @php
                    $count = $bucket['rfp'] + $bucket['p2p'];
                    $percent = round($count / $total * 100);
                @endphp
                <div>
                    <div class="mb-1 flex justify-between gap-2 text-xs">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $bucket['bucket'] }} <span class="font-normal text-gray-400">· {{ $bucket['rfp'] }} RFP · {{ $bucket['p2p'] }} P2P</span></span>
                        <span class="font-bold tabular-nums {{ $counts[$loop->index] ?? $counts[0] }}">{{ $count }} {{ str('item')->plural($count) }} <span class="font-normal text-gray-400">({{ $percent }}%)</span></span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2.5 rounded-full {{ $bars[$loop->index] ?? $bars[0] }}" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-[0.6875rem] text-gray-500 dark:border-white/5 dark:text-gray-400">
        <span>Oldest first in your work queue</span>
        @if ($olderThanFive)
            <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $olderThanFive }} {{ str('item')->plural($olderThanFive) }} older than 5 days</span>
        @endif
    </div>
</section>
