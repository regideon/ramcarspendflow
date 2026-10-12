@php
    use App\Support\Peso;

    $stages = collect($stages);
    $total = $stages->sum('amount');
    $count = $stages->sum('count');
    $max = max($stages->max('count'), 1);
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Pipeline" description="My open requests by stage" class="h-full">
        <x-slot name="afterHeader">
            <x-filament::badge color="gray">{{ $count }} open</x-filament::badge>
        </x-slot>

        <div class="flex flex-col gap-4">
            @foreach ($stages as $stage)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $stage['stage'] }}</span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $stage['count'] }} {{ str('request')->plural($stage['count']) }} ·
                            <strong class="text-gray-950 dark:text-white">{{ Peso::format($stage['amount']) }}</strong>
                        </span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-info-500" style="width: {{ round($stage['count'] / $max * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
            <span class="text-gray-500 dark:text-gray-400">Total value in the pipeline</span>
            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($total) }}</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
