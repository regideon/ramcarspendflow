<span class="inline-flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-[0.6875rem] font-semibold text-primary-700 ring-1 ring-primary-200/70 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
        <span class="size-1.5 rounded-full bg-primary-500"></span>
        {{ $role }}
    </span>
    <span class="text-gray-300 dark:text-gray-600">•</span>
    <span class="inline-flex items-center gap-1.5">
        <span class="size-2 rounded-full bg-primary-500"></span>
        <strong class="font-semibold tabular-nums text-gray-800 dark:text-gray-100">{{ number_format($mine) }} for my action</strong>
    </span>
    @if ($waiting > 0)
        <span class="text-gray-300 dark:text-gray-600">•</span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-50 px-2.5 py-0.5 text-[0.6875rem] font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">
            <span class="size-2 animate-pulse rounded-full bg-warning-500"></span>
            {{ number_format($waiting) }} waiting more than {{ \App\Filament\Pages\P2p\ApprovalQueue::WAITING_DAYS }} days
        </span>
    @endif
</span>
