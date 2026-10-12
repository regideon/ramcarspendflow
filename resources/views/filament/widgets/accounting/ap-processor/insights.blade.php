@php
    use App\Filament\Pages\KnowledgeBase;

    $decisions = collect($decisions);
    $decisionTotal = max($decisions->sum('count'), 1);
    $reasons = collect($reasons);
    $maxReason = max($reasons->max('count'), 1);

    $bar = fn (string $color): string => match ($color) {
        'success' => 'bg-success-500',
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        default => 'bg-primary-500',
    };
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <x-filament::section heading="My decisions" description="Items I finished this week">
            <x-slot name="afterHeader">
                <x-filament::badge color="gray">{{ $decisions->sum('count') }}</x-filament::badge>
            </x-slot>

            <div class="flex flex-col gap-3">
                @foreach ($decisions as $decision)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-gray-950 dark:text-white">{{ $decision['label'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $decision['count'] }} <span class="font-normal text-gray-500">({{ round($decision['count'] / $decisionTotal * 100) }}%)</span></span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full {{ $bar($decision['color']) }}" style="width: {{ round($decision['count'] / $decisionTotal * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-slot name="footer">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Returned by AP Supervisor: <strong class="text-gray-950 dark:text-white">{{ number_format($returnRate['rate'], 1) }}%</strong>
                    <span @class(['font-medium', 'text-success-600 dark:text-success-400' => $returnRate['change'] <= 0, 'text-danger-600 dark:text-danger-400' => $returnRate['change'] > 0])>
                        ({{ sprintf('%+.1f', $returnRate['change']) }} pts vs last month)
                    </span>
                </p>
            </x-slot>
        </x-filament::section>

        <x-filament::section heading="Why my work came back" description="AP Supervisor return remarks">
            <div class="flex flex-col gap-3">
                @forelse ($reasons as $reason)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-gray-950 dark:text-white">{{ $reason['reason'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $reason['count'] }}×</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-warning-500" style="width: {{ round($reason['count'] / $maxReason * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nothing returned this period.</p>
                @endforelse
            </div>

            <x-slot name="footer">
                <x-filament::link :href="KnowledgeBase::getUrl()" icon="heroicon-m-book-open" size="sm">Tax &amp; WHT guide</x-filament::link>
            </x-slot>
        </x-filament::section>

        <x-filament::section heading="Recent activity" class="md:col-span-2 xl:col-span-1">
            <ol class="relative ms-1.5 flex flex-col gap-4 border-s-2 border-gray-200 ps-5 dark:border-white/10">
                @foreach ($activity as $event)
                    <li class="relative">
                        <span class="absolute -start-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-white dark:ring-gray-900 {{ $bar($event['tone']) }}"></span>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $event['text'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $event['at']->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ol>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
