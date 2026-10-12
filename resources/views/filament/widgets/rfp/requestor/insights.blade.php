@php
    use App\Support\Peso;

    $reasons = collect($reasons);
    $maxReason = max($reasons->max('count'), 1);

    $categories = collect($categories);
    $categoryTotal = max($categories->sum('amount'), 1);
    $palette = ['#f59e0b', '#3b82f6', '#64748b', '#cbd5e1', '#10b981', '#a855f7'];

    // Donut as a conic gradient: each category takes its share of the circle.
    $start = 0;
    $slices = $categories->values()->map(function (array $category, int $i) use (&$start, $categoryTotal, $palette): string {
        $end = $start + $category['amount'] / $categoryTotal * 100;
        $slice = $palette[$i % count($palette)]." {$start}% {$end}%";
        $start = $end;

        return $slice;
    })->implode(', ');

    $dot = fn (string $tone): string => match ($tone) {
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        default => 'bg-info-500',
    };
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <x-filament::section heading="Top return reasons" description="Why AP sent my requests back">
            <div class="flex flex-col gap-3">
                @foreach ($reasons as $reason)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-gray-950 dark:text-white">{{ $reason['reason'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $reason['count'] }}×</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-warning-500" style="width: {{ round($reason['count'] / $maxReason * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-slot name="footer">
                <x-filament::link :href="App\Filament\Pages\KnowledgeBase::getUrl()" icon="heroicon-m-book-open" size="sm">
                    How to avoid returns
                </x-filament::link>
            </x-slot>
        </x-filament::section>

        <x-filament::section heading="By category" description="My spend this period">
            <div class="flex items-center gap-5">
                <div class="relative h-28 w-28 shrink-0 rounded-full" style="background: conic-gradient({{ $slices }})">
                    <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white dark:bg-gray-900">
                        <span class="text-sm font-bold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($categories->sum('amount'), compact: true) }}</span>
                        <span class="text-[10px] text-gray-500">total</span>
                    </div>
                </div>
                <ul class="flex flex-1 flex-col gap-1.5 text-sm">
                    @foreach ($categories as $category)
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex min-w-0 items-center gap-1.5 text-gray-700 dark:text-gray-300">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $palette[$loop->index % count($palette)] }}"></span>
                                <span class="truncate">{{ $category['category'] }}</span>
                            </span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ round($category['amount'] / $categoryTotal * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-filament::section>

        <x-filament::section heading="Recent activity" class="md:col-span-2 xl:col-span-1">
            <ol class="relative ms-1.5 flex flex-col gap-4 border-s-2 border-gray-200 ps-5 dark:border-white/10">
                @foreach ($activity as $event)
                    <li class="relative">
                        <span class="absolute -start-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-white dark:ring-gray-900 {{ $dot($event['tone']) }}"></span>
                        <p class="text-sm text-gray-950 dark:text-white">
                            <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $event['rfp_number'] }}</span>
                            {{ $event['text'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $event['at']->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ol>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
