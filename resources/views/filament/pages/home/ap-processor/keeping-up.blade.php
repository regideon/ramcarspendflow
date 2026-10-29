@php
    use App\Support\SvgChart;

    $days = collect($throughput);
    $received = $days->pluck('received')->all();
    $completed = $days->pluck('completed')->all();
    $max = max([...$received, ...$completed]) + 1;

    $backlog = array_sum($received) - array_sum($completed);
    $rate = array_sum($received) ? round(array_sum($completed) / array_sum($received) * 100) : 0;

    // About five evenly spaced date labels, the last one "Today".
    $labelEvery = max(1, intdiv($days->count() - 1, 4));
@endphp

<section data-tour="keeping-up" class="sf-card flex flex-col justify-between p-6">
    <div>
        <x-sf.card-header heading="Keeping up" description="Items received vs items you finished, per day (last 14 days)">
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300"><span class="size-2.5 rounded-full bg-primary-600"></span>Finished</span>
                <span class="flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300"><span class="size-2.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>Received</span>
            </div>
        </x-sf.card-header>

        <div class="mt-4 h-36 w-full">
            <svg class="h-full w-full overflow-visible" viewBox="0 0 540 120" preserveAspectRatio="none" role="img" aria-label="Received vs finished per day">
                <defs>
                    <linearGradient id="sf-keeping-up-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--primary-500)" stop-opacity="0.18" />
                        <stop offset="100%" stop-color="var(--primary-500)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                @foreach ([20, 60, 100] as $y)
                    <line x1="0" y1="{{ $y }}" x2="540" y2="{{ $y }}" class="stroke-gray-100 dark:stroke-white/5" stroke-width="1" vector-effect="non-scaling-stroke" />
                @endforeach
                <polygon points="{{ SvgChart::area($completed, 540, 120, max: $max, padding: 6) }}" fill="url(#sf-keeping-up-fill)" />
                <polyline points="{{ SvgChart::points($received, 540, 120, max: $max, padding: 6) }}" fill="none" class="stroke-gray-300 dark:stroke-gray-600" stroke-width="2.5" stroke-dasharray="4 3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                <polyline points="{{ SvgChart::points($completed, 540, 120, max: $max, padding: 6) }}" fill="none" stroke="var(--primary-600)" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            </svg>
        </div>

        <div class="mt-2 flex justify-between font-mono text-[0.625rem] text-gray-400">
            @foreach ($days as $i => $day)
                @if ($loop->last)
                    <span>Today</span>
                @elseif ($i % $labelEvery === 0 && $days->count() - 1 - $i >= $labelEvery / 2)
                    <span>{{ $day['day'] }}</span>
                @endif
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-[0.6875rem] dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">
            Backlog:
            <strong @class(['font-semibold tabular-nums', 'text-success-700 dark:text-success-400' => $backlog <= 0, 'text-danger-600 dark:text-danger-400' => $backlog > 0])>
                {{ $backlog <= 0 ? 'down '.abs($backlog) : 'up '.$backlog }} in 14 days
            </strong>
        </span>
        <span class="font-semibold tabular-nums text-primary-600 dark:text-primary-400">You finished {{ $rate }}% of what came in</span>
    </div>
</section>
