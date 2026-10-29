{{-- Card title: icon tile, title and an optional line under it. --}}
<div class="flex items-start gap-3">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
        <x-filament::icon :icon="$icon" class="size-5" />
    </span>
    <div>
        <h2 class="font-display text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
        @if ($subtitle ?? null)
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        @endif
    </div>
</div>
