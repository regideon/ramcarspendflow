@php
    use App\Support\Peso;

    // Per chip colour: top bar, card tint, icon tile, number/amount text.
    $tones = [
        'warning' => ['bar' => 'bg-warning-400', 'card' => '', 'icon' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400', 'text' => 'text-warning-700 dark:text-warning-400'],
        'gray' => ['bar' => 'bg-gray-300 dark:bg-gray-600', 'card' => '', 'icon' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300', 'text' => 'text-gray-900 dark:text-white'],
        'info' => ['bar' => 'bg-primary-600', 'card' => 'bg-primary-50/60 dark:bg-primary-500/5', 'icon' => 'bg-primary-600 text-white', 'text' => 'text-primary-700 dark:text-primary-300'],
        'danger' => ['bar' => 'bg-danger-500', 'card' => 'bg-danger-50/40 dark:bg-danger-500/5', 'icon' => 'bg-danger-100 text-danger-600 dark:bg-danger-500/15 dark:text-danger-400', 'text' => 'text-danger-600 dark:text-danger-400'],
        'success' => ['bar' => 'bg-success-500', 'card' => 'bg-success-50/40 dark:bg-success-500/5', 'icon' => 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400', 'text' => 'text-success-700 dark:text-success-400'],
    ];

    $captions = [
        'returned' => 'Fix and resubmit',
        'drafts' => 'Not yet submitted',
        'in_progress' => 'With approvers or AP',
        'overdue' => 'Past their due date',
        'paid_this_month' => 'Payment released',
    ];
@endphp

<section data-tour="chips" class="grid grid-cols-2 gap-3.5 md:grid-cols-3 xl:grid-cols-5">
    @foreach ($this->chips as ['chip' => $chip, 'count' => $count, 'amount' => $amount, 'active' => $active])
        @php $tone = $tones[$chip->color] ?? $tones['gray']; @endphp
        <button
            type="button"
            wire:click="pickFocus('{{ $chip->key }}')"
            @class([
                'sf-card group relative flex flex-col justify-between overflow-hidden p-4 text-start transition hover:shadow-md',
                $tone['card'],
                '!border-primary-400 ring-2 ring-primary-500/30' => $active,
            ])
        >
            <span class="absolute inset-x-0 top-0 h-1 {{ $tone['bar'] }}"></span>
            <div class="mb-2 flex items-center justify-between gap-2">
                <span @class(['text-xs font-semibold', $tone['text'] => $chip->color !== 'gray', 'text-gray-500 dark:text-gray-400' => $chip->color === 'gray'])>{{ $chip->label }}</span>
                <span class="flex size-7 items-center justify-center rounded-full {{ $tone['icon'] }}">
                    <x-filament::icon :icon="$chip->icon" class="size-4" />
                </span>
            </div>
            <div class="flex items-baseline justify-between gap-2">
                <span class="font-display text-xl font-bold tabular-nums transition group-hover:scale-105 {{ $tone['text'] }}">{{ number_format($count) }}</span>
                <span class="font-display text-sm font-semibold tabular-nums {{ $tone['text'] }}">{{ Peso::format($amount, compact: true) }}</span>
            </div>
            <span class="mt-1 text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $active ? 'Showing these · click to clear' : $captions[$chip->key] ?? '' }}</span>
        </button>
    @endforeach
</section>
