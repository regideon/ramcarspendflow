@php
    // Per chip colour: pill and count.
    $tones = [
        'danger' => ['pill' => 'bg-danger-50 text-danger-700 ring-danger-200/70 hover:bg-danger-100 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/30', 'icon' => 'text-danger-600 dark:text-danger-400'],
        'warning' => ['pill' => 'bg-warning-50 text-warning-800 ring-warning-200/70 hover:bg-warning-100 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30', 'icon' => 'text-warning-600 dark:text-warning-400'],
        'info' => ['pill' => 'bg-primary-50 text-primary-700 ring-primary-200/70 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30', 'icon' => 'text-primary-600 dark:text-primary-400'],
        'gray' => ['pill' => 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10', 'icon' => 'text-gray-500 dark:text-gray-400'],
    ];
@endphp

<section data-tour="chips" class="flex items-center gap-2.5 overflow-x-auto pb-1">
    @foreach ($this->chips as ['chip' => $chip, 'count' => $count, 'active' => $active])
        @php $tone = $tones[$chip->color] ?? $tones['gray']; @endphp
        <button
            type="button"
            wire:click="pickFocus('{{ $chip->key }}')"
            title="{{ $active ? 'Showing these · click to clear' : 'Show only these' }}"
            @class([
                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm ring-1 transition',
                $tone['pill'],
                '!ring-2 !ring-primary-500' => $active,
            ])
        >
            <x-filament::icon :icon="$chip->icon" class="size-4 {{ $tone['icon'] }}" />
            {{ $chip->label }}
            <span class="ms-1 min-w-5 rounded-full bg-white px-1.5 py-0.5 text-center text-[0.6875rem] font-bold tabular-nums shadow-sm dark:bg-gray-900">{{ number_format($count) }}</span>
            @if ($active)
                <x-filament::icon icon="heroicon-m-x-mark" class="size-3.5" />
            @endif
        </button>
    @endforeach
</section>
