@php
    $stages = collect($stages);
    $max = max($stages->max('days'), 0.1);
    $longest = $stages->sortByDesc('days')->first();
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Where my requests wait" description="Average days per stage, last 90 days" class="h-full">
        <div class="flex flex-col gap-4">
            @foreach ($stages as $stage)
                @php($isLongest = $stage === $longest)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $stage['stage'] }}</span>
                        <span @class(['tabular-nums', 'font-semibold text-danger-600 dark:text-danger-400' => $isLongest, 'text-gray-500 dark:text-gray-400' => ! $isLongest])>
                            {{ number_format($stage['days'], 1) }} days{{ $isLongest ? ' · longest' : '' }}
                        </span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div @class(['h-full rounded-full', 'bg-danger-500' => $isLongest, 'bg-gray-400 dark:bg-gray-500' => ! $isLongest]) style="width: {{ round($stage['days'] / $max * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($longest)
            <div class="mt-5 flex items-start gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm text-gray-700 dark:bg-warning-500/10 dark:text-gray-300">
                <x-filament::icon icon="heroicon-m-light-bulb" class="mt-0.5 h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" />
                <p><strong class="font-semibold">Tip:</strong> {{ $longest['stage'] }} takes the longest. Submit early, with complete attachments, to be paid on time.</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
