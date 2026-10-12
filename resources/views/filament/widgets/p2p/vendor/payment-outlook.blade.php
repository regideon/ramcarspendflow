@php
    use App\Support\Peso;

    $buckets = collect($buckets);
    $total = max($buckets->sum('net'), 1);
    $bar = fn (string $color): string => match ($color) {
        'success' => 'bg-success-500',
        'primary' => 'bg-primary-500',
        default => 'bg-info-500',
    };
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Payment outlook" description="Net payable on approved invoices, after withholding tax" class="h-full">
        <div class="flex flex-col gap-3">
            @foreach ($buckets as $bucket)
                <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $bar($bucket['color']) }}"></span>
                            {{ $bucket['label'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $bucket['hint'] }} · {{ $bucket['count'] }} {{ str('invoice')->plural($bucket['count']) }}</div>
                    </div>
                    <span class="whitespace-nowrap font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($bucket['net']) }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
            @foreach ($buckets as $bucket)
                <div class="h-full {{ $bar($bucket['color']) }}" style="width: {{ round($bucket['net'] / $total * 100, 1) }}%" title="{{ $bucket['label'] }}"></div>
            @endforeach
        </div>

        <div class="mt-5 flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
            <p>Net = invoice total − EWT. Ramcar AP computes VAT and EWT when it reviews your invoice.</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
