{{-- Urgency chips above an RFP list (App\Filament\Concerns\HasQueueChips). --}}
@php
    $tones = [
        'danger' => 'border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300',
        'warning' => 'border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-300',
        'info' => 'border-info-300 bg-info-50 text-info-700 dark:border-info-500/40 dark:bg-info-500/10 dark:text-info-300',
        'success' => 'border-success-300 bg-success-50 text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-300',
        'gray' => 'border-gray-300 bg-gray-50 text-gray-700 dark:border-white/15 dark:bg-white/5 dark:text-gray-200',
    ];
    $idle = 'border-gray-200 bg-white text-gray-400 dark:border-white/10 dark:bg-white/5 dark:text-gray-500';
@endphp

<div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="Quick filters">
    @foreach ($chips as $state)
        @php
            $chip = $state['chip'];
            $tone = $state['count'] > 0 ? $tones[$chip->color] ?? $tones['gray'] : $idle;
        @endphp

        <button
            type="button"
            wire:click="toggleQueueChip('{{ $chip->key }}')"
            wire:key="queue-chip-{{ $chip->key }}"
            @disabled($state['count'] === 0 && ! $state['active'])
            aria-pressed="{{ $state['active'] ? 'true' : 'false' }}"
            @class([
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition',
                'hover:shadow-sm disabled:cursor-default disabled:hover:shadow-none',
                $tone,
                'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900' => $state['active'],
            ])
        >
            <x-filament::icon :icon="$chip->icon" class="h-4 w-4" />
            <span class="font-semibold tabular-nums">{{ number_format($state['count']) }}</span>
            <span>{{ $chip->label }}</span>
            @if ($state['amount'] !== null && $state['count'] > 0)
                <span class="opacity-75 tabular-nums">· PHP {{ number_format($state['amount'], 2) }}</span>
            @endif
            @if ($state['active'] && $chip->filters())
                <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
            @endif
        </button>
    @endforeach
</div>
