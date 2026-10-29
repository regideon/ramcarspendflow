<span class="inline-flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-[0.6875rem] font-semibold text-primary-700 ring-1 ring-primary-200/70 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
        <x-filament::icon icon="heroicon-m-briefcase" class="size-3" />
        {{ $role }}
    </span>
    <span class="text-gray-300 dark:text-gray-600">•</span>
    <span><strong class="font-semibold tabular-nums text-gray-800 dark:text-gray-100">{{ $total }} {{ str('item')->plural($total) }}</strong> in your queue</span>
    @if ($overdue)
        <span class="text-gray-300 dark:text-gray-600">•</span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-danger-50 px-2 py-0.5 text-xs font-semibold text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
            <span class="size-1.5 animate-pulse rounded-full bg-danger-500"></span>
            {{ $overdue }} overdue
        </span>
    @endif
</span>
