<x-filament-widgets::widget>
    <x-filament::section compact>
        <ol class="rfp-progress" aria-label="RFP approval progress">
            @foreach ($this->getSteps() as $step)
                <li
                    @class(['rfp-progress-step', 'is-'.$step['state']])
                    @if (in_array($step['state'], ['current', 'revision', 'failed'], true)) aria-current="step" @endif
                >
                    <span class="rfp-progress-icon">
                        <x-filament::icon :icon="$step['icon']" class="rfp-progress-svg" />
                    </span>
                    <span class="rfp-progress-text">
                        <span class="rfp-progress-label">{{ $step['label'] }}</span>
                        <span class="rfp-progress-who">{{ $step['note'] ?? $step['who'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </x-filament::section>

    <style>
        .rfp-progress { display: flex; gap: .5rem; overflow-x: auto; padding: .25rem 0; margin: 0; list-style: none; }
        .rfp-progress-step { position: relative; flex: 1 1 0; min-width: 7.5rem; display: flex; flex-direction: column; align-items: center; text-align: center; gap: .5rem; }
        .rfp-progress-step:not(:last-child)::after { content: ''; position: absolute; top: 1.125rem; left: calc(50% + 1.5rem); right: calc(-50% + 1.25rem); height: 2px; border-radius: 1px; background: var(--gray-200); }
        .dark .rfp-progress-step:not(:last-child)::after { background: var(--gray-700); }
        .rfp-progress-step.is-done:not(:last-child)::after,
        .rfp-progress-step.is-skipped:not(:last-child)::after { background: var(--success-500); }
        .rfp-progress-icon { position: relative; z-index: 1; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 9999px; color: var(--gray-400); background: var(--gray-100); }
        .dark .rfp-progress-icon { color: var(--gray-500); background: var(--gray-800); }
        .rfp-progress-svg { width: 1.25rem; height: 1.25rem; }
        .rfp-progress-text { display: flex; flex-direction: column; gap: .125rem; }
        .rfp-progress-label { font-size: .875rem; font-weight: 600; color: var(--gray-500); }
        .rfp-progress-who { font-size: .75rem; color: var(--gray-500); }
        .dark .rfp-progress-label, .dark .rfp-progress-who { color: var(--gray-400); }

        .rfp-progress-step.is-done .rfp-progress-icon { color: var(--success-600); background: color-mix(in oklab, var(--success-500) 15%, transparent); }
        .rfp-progress-step.is-skipped .rfp-progress-icon { color: var(--gray-500); background: color-mix(in oklab, var(--success-500) 10%, transparent); }
        .rfp-progress-step.is-current .rfp-progress-icon { color: #fff; background: var(--primary-500); box-shadow: 0 0 0 4px color-mix(in oklab, var(--primary-500) 25%, transparent); }
        .rfp-progress-step.is-revision .rfp-progress-icon { color: #fff; background: var(--warning-500); box-shadow: 0 0 0 4px color-mix(in oklab, var(--warning-500) 25%, transparent); }
        .rfp-progress-step.is-failed .rfp-progress-icon { color: #fff; background: var(--danger-500); box-shadow: 0 0 0 4px color-mix(in oklab, var(--danger-500) 25%, transparent); }
        .rfp-progress-step:is(.is-done, .is-current, .is-revision, .is-failed) .rfp-progress-label { color: var(--gray-950); }
        .dark .rfp-progress-step:is(.is-done, .is-current, .is-revision, .is-failed) .rfp-progress-label { color: #fff; }
        .rfp-progress-step.is-revision .rfp-progress-who { color: var(--warning-600); }
        .rfp-progress-step.is-failed .rfp-progress-who { color: var(--danger-600); }
    </style>
</x-filament-widgets::widget>
