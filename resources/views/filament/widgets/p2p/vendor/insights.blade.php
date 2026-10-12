@php
    use App\Enums\P2pDownPaymentStatus;
    use App\Support\Peso;

    $months = collect($months);
    $max = max($months->max('billed'), $months->max('paid'), 1);
    $collected = $months->sum('billed') > 0 ? $months->sum('paid') / $months->sum('billed') * 100 : 0;

    $dpColor = fn (P2pDownPaymentStatus $status): string => match ($status) {
        P2pDownPaymentStatus::Requested => 'warning',
        P2pDownPaymentStatus::Paid => 'info',
        P2pDownPaymentStatus::Cleared => 'success',
    };

    $dot = fn (string $tone): string => match ($tone) {
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        default => 'bg-info-500',
    };
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <x-filament::section heading="Billed vs paid" description="Last 6 months">
            <div class="flex flex-col gap-3">
                @foreach ($months->reverse() as $month)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $month['month'] }}</span>
                            <span class="tabular-nums">{{ Peso::format($month['billed'], compact: true) }} billed · {{ Peso::format($month['paid'], compact: true) }} paid</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <div class="h-1.5 rounded-full bg-primary-500" style="width: {{ round($month['billed'] / $max * 100) }}%"></div>
                            <div class="h-1.5 rounded-full bg-success-500" style="width: {{ round($month['paid'] / $max * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-3">
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-primary-500"></span> Billed</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-success-500"></span> Paid (net)</span>
                    </span>
                    <span class="font-semibold text-success-600 dark:text-success-400">{{ number_format($collected, 1) }}% paid</span>
                </div>
            </x-slot>
        </x-filament::section>

        <x-filament::section heading="Down payments" description="Requested through RFP, per PO">
            <div class="flex flex-col gap-3">
                @forelse ($downPayments as $downPayment)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-950 dark:text-white">PO {{ $downPayment['po'] }}</div>
                            <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $downPayment['description'] }}</div>
                        </div>
                        <div class="text-end">
                            <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($downPayment['amount']) }}</div>
                            <x-filament::badge :color="$dpColor($downPayment['status'])" size="sm" class="mt-1 inline-flex">{{ $downPayment['status']->getLabel() }}</x-filament::badge>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No down payments requested.</p>
                @endforelse
            </div>

            <x-slot name="footer">
                <p class="text-xs text-gray-500 dark:text-gray-400">The down payment is deducted when you bill the balance.</p>
            </x-slot>
        </x-filament::section>

        <x-filament::section heading="Recent activity" class="md:col-span-2 xl:col-span-1">
            <ol class="relative ms-1.5 flex flex-col gap-4 border-s-2 border-gray-200 ps-5 dark:border-white/10">
                @foreach ($activity as $event)
                    <li class="relative">
                        <span class="absolute -start-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-white dark:ring-gray-900 {{ $dot($event['tone']) }}"></span>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $event['text'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $event['at']->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ol>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
