@php
    use App\Support\Peso;

    $items = collect($items);
    $overdue = $items->filter(fn (array $item): bool => $item['due_date']->isPast());
    $upcoming = $items->reject(fn (array $item): bool => $item['due_date']->isPast());
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Upcoming due dates" description="Open requests due in the next 14 days" class="h-full">
        <x-slot name="afterHeader">
            <x-filament::badge color="gray">{{ $upcoming->count() }} due soon</x-filament::badge>
        </x-slot>

        <div class="flex flex-col gap-2">
            @foreach ($overdue as $item)
                <div class="flex items-center justify-between gap-3 rounded-lg border-s-4 border-danger-500 bg-danger-50 p-3 dark:bg-danger-500/10">
                    <div class="flex min-w-0 items-start gap-2">
                        <x-filament::icon icon="heroicon-m-exclamation-circle" class="mt-0.5 h-5 w-5 shrink-0 text-danger-600 dark:text-danger-400" />
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-danger-700 dark:text-danger-400">{{ $item['rfp_number'] }} · {{ $item['purpose'] }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                {{ (int) $item['due_date']->diffInDays(today()) }}d overdue · now at {{ $item['status']->getLabel() }}
                            </div>
                        </div>
                    </div>
                    <span class="whitespace-nowrap font-semibold tabular-nums text-danger-700 dark:text-danger-400">{{ Peso::format($item['amount']) }}</span>
                </div>
            @endforeach

            @forelse ($upcoming as $item)
                <div class="flex items-center justify-between gap-3 rounded-lg p-2.5 hover:bg-gray-50 dark:hover:bg-white/5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="w-14 shrink-0 rounded-md bg-gray-100 py-1 text-center text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $item['due_date']->format('M j') }}</span>
                        <div class="min-w-0">
                            <div class="truncate font-medium text-gray-950 dark:text-white">{{ $item['payee'] }}</div>
                            <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['purpose'] }} · {{ $item['status']->getLabel() }}</div>
                        </div>
                    </div>
                    <span class="whitespace-nowrap font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($item['amount']) }}</span>
                </div>
            @empty
                @if ($overdue->isEmpty())
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nothing due in the next 14 days.</p>
                @endif
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
