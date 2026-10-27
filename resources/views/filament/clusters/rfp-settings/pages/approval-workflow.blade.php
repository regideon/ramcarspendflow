@php
    use App\Enums\RfpRequestType;
    use App\Enums\RfpStageKind;

    $nodes = $this->diagram();
    $companyName = $this->previewCompanies()[$this->previewCompanyId] ?? 'this company';
@endphp

<x-filament-panels::page>
    <x-filament::section
        heading="Flow"
        description="How a sample request would move through the workflow below, and who holds each role in the chosen company. Updates as you edit; click a stage to jump to its settings."
    >
        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                Company
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="previewCompanyId">
                        @foreach ($this->previewCompanies() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                Request type
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="previewRequestType">
                        @foreach (RfpRequestType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                Amount (PHP)
                <x-filament::input.wrapper prefix="PHP">
                    <x-filament::input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="previewAmount" />
                </x-filament::input.wrapper>
            </label>
            <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <x-filament::input.checkbox wire:model.live="previewDirectToVp" />
                Direct to VP
            </label>
        </div>

        <ol class="flex flex-wrap items-stretch gap-x-2 gap-y-4" aria-label="Approval flow">
            <li class="flex items-center gap-2">
                <div class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-semibold text-gray-600 dark:border-white/20 dark:text-gray-300">
                    Requestor
                </div>
                <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4 shrink-0 text-gray-400" />
            </li>

            @foreach ($nodes as $index => $node)
                <li class="flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="$el.closest('.fi-page').querySelectorAll('.fi-fo-repeater-item')[{{ $index }}]?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                        @class([
                            'flex h-full min-w-44 max-w-64 flex-col gap-2 rounded-lg border p-3 text-start shadow-sm transition hover:ring-2 hover:ring-primary-500/40',
                            'border-primary-300 bg-primary-50 dark:border-primary-500/40 dark:bg-primary-500/10' => $node['kind'] === RfpStageKind::Matrix,
                            'border-success-300 bg-success-50 dark:border-success-500/40 dark:bg-success-500/10' => $node['kind'] === RfpStageKind::Payment,
                            'border-gray-200 bg-white dark:border-white/10 dark:bg-white/5' => ! in_array($node['kind'], [RfpStageKind::Matrix, RfpStageKind::Payment], true),
                        ])
                    >
                        <span class="flex items-baseline gap-2">
                            <span class="text-xs font-semibold text-gray-400">{{ $node['position'] }}</span>
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $node['label'] }}</span>
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $node['kind']->getLabel() }}</span>

                        @if ($node['note'])
                            <span class="text-xs italic text-gray-600 dark:text-gray-300">{{ $node['note'] }}</span>
                        @elseif ($node['sequences'] === [])
                            <span class="text-xs font-medium text-danger-600 dark:text-danger-400">⚠ No role set</span>
                        @endif

                        @foreach ($node['sequences'] as $sequence)
                            <span class="flex flex-col gap-1 border-t border-gray-200/70 pt-2 first-of-type:border-0 first-of-type:pt-0 dark:border-white/10">
                                @if (count($sequence) > 1)
                                    <span class="text-[11px] font-medium uppercase tracking-wide text-gray-400">In parallel</span>
                                @endif
                                @foreach ($sequence as $step)
                                    <span class="text-xs">
                                        @if ($node['kind'] === RfpStageKind::Matrix || $step['role'] !== $node['label'])
                                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ $step['role'] }}</span><br>
                                        @endif
                                        @if ($step['people'] === [])
                                            <span class="font-medium text-danger-600 dark:text-danger-400" title="Nobody holds {{ $step['role'] }} in {{ $companyName }}, so requests would get stuck here.">⚠ Nobody in this company</span>
                                        @else
                                            <span class="text-gray-600 dark:text-gray-300">{{ implode(', ', $step['people']) }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </span>
                        @endforeach

                        @if ($node['returnsToRequestor'] || $node['returnsTo'] || $node['canReject'] || $node['canCancel'])
                            <span class="mt-auto flex flex-wrap gap-1 pt-1">
                                @if ($node['returnsToRequestor'])
                                    <x-filament::badge size="sm" color="warning" icon="heroicon-m-arrow-uturn-left">Requestor</x-filament::badge>
                                @endif
                                @if ($node['returnsTo'])
                                    <x-filament::badge size="sm" color="info" icon="heroicon-m-arrow-uturn-left">{{ $node['returnsTo'] }}</x-filament::badge>
                                @endif
                                @if ($node['canReject'])
                                    <x-filament::badge size="sm" color="danger" icon="heroicon-m-x-circle">Reject</x-filament::badge>
                                @endif
                                @if ($node['canCancel'])
                                    <x-filament::badge size="sm" color="gray" icon="heroicon-m-no-symbol">Cancel</x-filament::badge>
                                @endif
                            </span>
                        @endif
                    </button>

                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4 shrink-0 text-gray-400" />
                </li>
            @endforeach

            <li class="flex items-center">
                <div class="rounded-lg border border-dashed border-success-400 px-3 py-2 text-sm font-semibold text-success-700 dark:text-success-400">
                    Paid
                </div>
            </li>
        </ol>

        <p class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
            <span>↩ badges: where the stage can send the request back</span>
            <span>⚠ Nobody in this company: assign the role under Users → Approval roles by company</span>
        </p>
    </x-filament::section>

    {{ $this->form }}
</x-filament-panels::page>
