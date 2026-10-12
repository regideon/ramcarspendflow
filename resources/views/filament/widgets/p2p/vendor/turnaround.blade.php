@php
    $stages = collect($turnaround['stages']);
    $max = max($stages->max('days'), 0.1);
    $faster = $turnaround['change'] <= 0;
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Invoice-to-payment time" description="Average days from submitting an invoice to being paid" class="h-full">
        <div class="mb-5 flex flex-wrap items-baseline gap-3">
            <span class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($turnaround['total'], 1) }} days</span>
            <span @class(['inline-flex items-center gap-1 text-sm font-semibold', 'text-success-600 dark:text-success-400' => $faster, 'text-danger-600 dark:text-danger-400' => ! $faster])>
                <x-filament::icon :icon="$faster ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up'" class="h-4 w-4" />
                {{ sprintf('%+.1f', $turnaround['change']) }} days vs last quarter
            </span>
        </div>

        <div class="flex flex-col gap-4">
            @foreach ($stages as $stage)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $stage['stage'] }}</span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($stage['days'], 1) }} days</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-info-500" style="width: {{ round($stage['days'] / $max * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex items-start gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm text-gray-700 dark:bg-warning-500/10 dark:text-gray-300">
            <x-filament::icon icon="heroicon-m-light-bulb" class="mt-0.5 h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" />
            <p><strong class="font-semibold">Tip:</strong> bill soon after delivery, and make sure the invoice date and reference number match the PDF, to avoid returns.</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
