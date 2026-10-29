<span class="inline-flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-[0.6875rem] font-semibold text-primary-700 ring-1 ring-primary-200/70 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
        <x-filament::icon icon="heroicon-m-briefcase" class="size-3" />
        {{ $role }}
    </span>
    <span class="text-gray-300 dark:text-gray-600">•</span>
    <span><strong class="font-semibold tabular-nums text-gray-800 dark:text-gray-100">{{ number_format($total) }} {{ str('request')->plural($total) }}</strong></span>
    <span class="text-gray-300 dark:text-gray-600">•</span>
    <span><strong class="font-semibold tabular-nums text-gray-800 dark:text-gray-100">{{ $inProgress }}</strong> in progress</span>
</span>
