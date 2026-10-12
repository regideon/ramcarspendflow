@php
    $buckets = collect($buckets)->map(fn (array $bucket): array => [...$bucket, 'total' => $bucket['rfp'] + $bucket['p2p']]);
    $total = max($buckets->sum('total'), 1);
    $bar = fn (string $color): string => match ($color) {
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        default => 'bg-gray-400',
    };
    $late = $buckets->whereIn('color', ['warning', 'danger'])->sum('total');
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Queue aging" description="How long items have been waiting on you" class="h-full">
        <div class="mb-4 flex h-7 w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-white/10">
            @foreach ($buckets as $bucket)
                @if ($bucket['total'])
                    <div class="flex h-full items-center justify-center text-[11px] font-bold text-white {{ $bar($bucket['color']) }}" style="width: {{ round($bucket['total'] / $total * 100, 1) }}%" title="{{ $bucket['bucket'] }}: {{ $bucket['total'] }}">
                        {{ $bucket['total'] }}
                    </div>
                @endif
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($buckets as $bucket)
                <div @class(['flex flex-col rounded-lg p-2.5', 'bg-danger-50 dark:bg-danger-500/10' => $bucket['color'] === 'danger', 'bg-gray-50 dark:bg-white/5' => $bucket['color'] !== 'danger'])>
                    <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span class="h-2 w-2 rounded-full {{ $bar($bucket['color']) }}"></span>
                        {{ $bucket['bucket'] }}
                    </span>
                    <span class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $bucket['total'] }} {{ str('item')->plural($bucket['total']) }}</span>
                    <span class="text-[11px] text-gray-500">{{ $bucket['rfp'] }} RFP · {{ $bucket['p2p'] }} P2P</span>
                </div>
            @endforeach
        </div>

        @if ($late)
            <p class="mt-4 flex items-center gap-1.5 text-sm text-warning-700 dark:text-warning-400">
                <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4" />
                {{ $late }} {{ str('item')->plural($late) }} waiting 6 days or more.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
