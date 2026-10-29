@php
    $tabs = $this->tabs();
    $counts = $this->tabCounts;
    $groups = collect($tabs)->groupBy('group', preserveKeys: true);
@endphp

{{-- Tabs follow the route: For my action · 1–6 steps · outcomes --}}
<div data-tour="tabs" class="flex items-center gap-1 overflow-x-auto border-b border-gray-100 bg-gray-50/60 px-3 py-3 dark:border-white/5 dark:bg-white/[0.02]" role="tablist">
    @foreach ($groups as $group => $groupTabs)
        @if (! $loop->first && $group === 'outcome')
            <div class="mx-1 h-6 w-px shrink-0 bg-gray-200 dark:bg-white/10"></div>
        @endif

        <div @class(['flex shrink-0 items-center gap-1', 'px-2' => $group === 'route'])>
            @foreach ($groupTabs as $key => $item)
                @php
                    $isActive = $tab === $key;
                    $count = $counts[$key] ?? null;
                @endphp

                @if ($group === 'route' && ! $loop->first)
                    <x-filament::icon icon="heroicon-m-chevron-right" class="size-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                @endif

                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-xs transition',
                        'bg-gray-900 font-semibold text-white shadow-sm dark:bg-white dark:text-gray-900' => $isActive && $group === 'mine',
                        'bg-white font-semibold text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-white/10 dark:text-white dark:ring-white/10' => $isActive && $group !== 'mine',
                        'font-semibold text-gray-700 hover:bg-white hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5' => ! $isActive && $group === 'mine',
                        'font-medium text-gray-500 hover:bg-white hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $isActive && $group !== 'mine',
                    ])
                >
                    @if (isset($item['step']))
                        <span @class([
                            'flex size-5 items-center justify-center rounded-full text-[0.6875rem] font-bold',
                            'bg-primary-600 text-white' => $isActive,
                            'bg-gray-200/70 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $isActive,
                        ])>{{ $item['step'] }}</span>
                    @else
                        <x-filament::icon :icon="$item['icon']" @class(['size-4', 'text-success-400 dark:text-success-600' => $isActive && $group === 'mine'])/>
                    @endif

                    {{ $item['label'] }}

                    @if ($group === 'mine')
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[0.6875rem] font-bold tabular-nums',
                            'bg-primary-600 text-white' => $isActive || $count > 0,
                            'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $isActive && ! $count,
                        ])>{{ number_format($count) }}</span>
                    @elseif ($count !== null)
                        <span @class([
                            'text-[0.6875rem] font-semibold tabular-nums',
                            'text-success-600 dark:text-success-400' => $key === 'completed',
                            'text-warning-600 dark:text-warning-400' => $key === 'for_revision' && $count > 0,
                            'text-danger-600 dark:text-danger-400' => $key === 'rejected' && $count > 0,
                            'text-gray-400' => ! in_array($key, ['completed', 'for_revision', 'rejected'], true) || ($key !== 'completed' && ! $count),
                        ])>({{ number_format($count) }})</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endforeach
</div>
