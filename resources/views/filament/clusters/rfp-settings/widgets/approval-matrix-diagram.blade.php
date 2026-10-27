@php
    use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\ApprovalRuleResource;

    $groups = $this->groups();
    $companyName = $this->companies()[$this->previewCompanyId] ?? 'this company';
    $peso = fn (string $amount): string => number_format((float) $amount, str_ends_with($amount, '.00') ? 0 : 2);
    $range = fn (?string $from, ?string $to): string => match (true) {
        $from === null && $to === null => 'Any amount',
        $from === null => 'Up to PHP '.$peso($to),
        $to === null => 'Above PHP '.$peso(bcsub($from, '0.01', 2)),
        default => 'PHP '.$peso($from).' to '.$peso($to),
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Matrix at a glance"
        description="Active tiers only. Approvers in a tier approve top to bottom; roles side by side approve in parallel. An amount exactly on a limit belongs to the lower tier."
    >
        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                Show approvers in
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="previewCompanyId">
                        @foreach ($this->companies() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                Highlight an amount
                <x-filament::input.wrapper prefix="PHP">
                    <x-filament::input type="number" min="0" step="0.01" placeholder="e.g. 150000" wire:model.live.debounce.500ms="previewAmount" />
                </x-filament::input.wrapper>
            </label>
        </div>

        <div class="grid gap-6">
            @forelse ($groups as $group)
                <div class="grid gap-2">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['label'] }}</h3>

                    <ol class="flex flex-wrap items-stretch gap-3">
                        @foreach ($group['tiers'] as $tier)
                            <li>
                                <a
                                    href="{{ ApprovalRuleResource::getUrl('edit', ['record' => $tier['id']]) }}"
                                    @class([
                                        'flex h-full min-w-48 max-w-72 flex-col gap-2 rounded-lg border p-3 shadow-sm transition hover:ring-2 hover:ring-primary-500/40',
                                        'border-primary-400 bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' => $tier['matches'],
                                        'border-gray-200 bg-white dark:border-white/10 dark:bg-white/5' => ! $tier['matches'],
                                    ])
                                >
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $range($tier['from'], $tier['to']) }}</span>
                                        @if ($tier['matches'])
                                            <x-filament::badge size="sm" color="primary">Matches</x-filament::badge>
                                        @endif
                                    </span>

                                    @forelse ($tier['sequences'] as $index => $sequence)
                                        @if ($index > 0)
                                            <x-filament::icon icon="heroicon-m-arrow-down" class="mx-auto h-3.5 w-3.5 text-gray-400" />
                                        @endif
                                        <span @class(['grid gap-2', 'grid-cols-2' => count($sequence) > 1])>
                                            @foreach ($sequence as $step)
                                                <span class="rounded-md bg-gray-50 px-2 py-1.5 text-xs dark:bg-white/5">
                                                    <span class="block font-medium text-gray-950 dark:text-white">{{ $step['role'] }}</span>
                                                    @if ($step['people'] === [])
                                                        <span class="font-medium text-danger-600 dark:text-danger-400" title="Nobody holds {{ $step['role'] }} in {{ $companyName }}, so requests in this tier would get stuck.">⚠ Nobody in this company</span>
                                                    @else
                                                        <span class="text-gray-600 dark:text-gray-300">{{ implode(', ', $step['people']) }}</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </span>
                                    @empty
                                        <span class="text-xs font-medium text-danger-600 dark:text-danger-400">⚠ No approvers</span>
                                    @endforelse
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No active tiers yet.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
