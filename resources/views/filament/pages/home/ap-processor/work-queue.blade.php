@php
    use App\Filament\Resources\P2p\Approvals\ApprovalResource;
    use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
    use App\Services\Accounting\ApProcessorDashboard;
    use App\Support\Peso;

    // Which tabs each row shows under, and the text the filter box searches.
    $rowMeta = array_map(fn (array $row): array => [
        'tabs' => ['all', strtolower($row['module']), ...array_values(array_intersect($row['tabs'], ['returned', 'overdue']))],
        'search' => (string) str(implode(' ', [$row['reference'], $row['sub'], $row['party'], $row['company'], $row['description'], $row['task']]))->lower(),
    ], $queue);

    $tabs = collect(['all' => 'All', 'rfp' => 'RFP', 'p2p' => 'P2P', 'returned' => 'Returned', 'overdue' => 'Overdue'])
        ->map(fn (string $label, string $key): array => [$label, collect($rowMeta)->filter(fn (array $meta): bool => in_array($key, $meta['tabs'], true))->count()]);

    // Age in my queue: up to 2 days grey, 3–5 amber, 6+ red.
    $ageClasses = fn (int $days): string => match (true) {
        $days <= 2 => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        $days <= 5 => 'bg-warning-50 text-warning-700 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/30',
        default => 'bg-danger-50 text-danger-600 ring-1 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/30',
    };
@endphp

<section
    data-tour="work-queue"
    class="sf-card p-6"
    x-data="{
        tab: 'all',
        search: '',
        rows: @js($rowMeta),
        visible(i) {
            return this.rows[i].tabs.includes(this.tab) && (this.search === '' || this.rows[i].search.includes(this.search.toLowerCase()))
        },
    }"
>
    <div class="flex flex-col gap-4 border-b border-gray-200 pb-4 xl:flex-row xl:items-center xl:justify-between dark:border-white/10">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-6">
            <div>
                <h2 class="font-display text-base font-bold text-gray-950 dark:text-white">Work queue</h2>
                <p class="text-xs text-gray-400">RFP and P2P items waiting on you, most urgent first</p>
            </div>

            <div class="flex w-max max-w-full items-center overflow-x-auto rounded-xl bg-gray-100 p-1 text-xs font-semibold dark:bg-white/5" role="tablist">
                @foreach ($tabs as $key => [$label, $count])
                    <button
                        type="button"
                        role="tab"
                        x-on:click="tab = @js($key)"
                        x-bind:aria-selected="tab === @js($key)"
                        x-bind:class="tab === @js($key) ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-800 dark:text-primary-300' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 transition"
                    >
                        {{ $label }}
                        <span @class([
                            'rounded-full px-1.5 text-[0.625rem] tabular-nums',
                            'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' => $key === 'overdue',
                            'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $key !== 'overdue',
                        ])>{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="relative xl:w-64">
            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                x-model.debounce.200ms="search"
                placeholder="Filter by reference, vendor, PO…"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-1.5 pe-3 ps-8 text-xs text-gray-700 placeholder-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
            />
        </div>
    </div>

    <div class="-mx-6 overflow-x-auto">
        <table class="w-full min-w-[68rem] text-start text-xs text-gray-600 dark:text-gray-300">
            <thead>
                <tr class="border-b border-gray-100 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-400 dark:border-white/5">
                    <th class="px-3 py-3 ps-6 text-start">Reference</th>
                    <th class="px-3 py-3 text-start">Type</th>
                    <th class="px-3 py-3 text-start">Payee / Vendor</th>
                    <th class="px-3 py-3 text-start">Company</th>
                    <th class="px-3 py-3 text-end">Amount (PHP)</th>
                    <th class="px-3 py-3 text-start">Stage</th>
                    <th class="px-3 py-3 text-center">Age</th>
                    <th class="px-3 py-3 text-start">Due</th>
                    <th class="px-3 py-3 pe-6 text-end"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($queue as $row)
                    @php
                        $overdueDays = $row['due_date']->lt(today()) ? (int) $row['due_date']->diffInDays(today()) : 0;
                        $isRework = $row['task'] === ApProcessorDashboard::TASK_REWORK;
                        $isEscalation = $row['task'] === ApProcessorDashboard::TASK_ESCALATION;
                    @endphp
                    <tr x-show="visible({{ $loop->index }})" class="transition hover:bg-gray-50/80 dark:hover:bg-white/5">
                        <td class="whitespace-nowrap px-3 py-3.5 ps-6">
                            <span @class([
                                'rounded px-2 py-0.5 font-mono text-[0.6875rem] font-semibold',
                                'bg-primary-50/80 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $row['module'] === 'RFP',
                                'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $row['module'] !== 'RFP',
                            ])>{{ $row['reference'] }}</span>
                            <div class="mt-1 text-[0.625rem] text-gray-400">{{ $row['sub'] }}</div>
                        </td>
                        <td class="px-3 py-3.5"><x-sf.module-chip :module="$row['module']" long /></td>
                        <td class="px-3 py-3.5">
                            <div class="max-w-52 truncate font-semibold text-gray-950 dark:text-white">{{ $row['party'] }}</div>
                            <div class="max-w-52 truncate text-[0.625rem] text-gray-400">{{ $row['description'] }}</div>
                        </td>
                        <td class="px-3 py-3.5 text-gray-700 dark:text-gray-300">{{ $row['company'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3.5 text-end">
                            <div class="text-sm font-bold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($row['amount']) }}</div>
                            @if ($row['foreign'])
                                <div class="text-[0.625rem] tabular-nums text-gray-400">{{ $row['foreign'] }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3.5">
                            <span @class([
                                'inline-flex items-center gap-1 whitespace-nowrap rounded px-2 py-0.5 text-[0.6875rem]',
                                'bg-warning-50 font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $isRework,
                                'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $isEscalation,
                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $isRework && ! $isEscalation,
                            ])>
                                @if ($isRework)
                                    <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="size-3" />
                                @elseif ($isEscalation)
                                    <x-filament::icon icon="heroicon-m-check-badge" class="size-3" />
                                @endif
                                {{ $row['task'] }}
                            </span>
                            @if ($row['match'] || $row['checks'])
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    @if ($row['match'])
                                        <x-filament::badge :color="$row['match']->getColor()" size="sm" :tooltip="$row['match']->description()">{{ $row['match']->getLabel() }}</x-filament::badge>
                                    @endif
                                    @foreach ($row['checks'] as [$label, $color])
                                        <x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge>
                                    @endforeach
                                </div>
                            @endif
                            @if ($row['ai_read'])
                                <div @class(['mt-1 text-[0.625rem]', 'text-danger-600 dark:text-danger-400' => $row['match']?->value === 'no_match', 'text-gray-400' => $row['match']?->value !== 'no_match'])>{{ $row['ai_read'] }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3.5 text-center">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[0.6875rem] font-bold tabular-nums {{ $ageClasses($row['age']) }}">{{ $row['age'] }}d</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3.5">
                            @if ($overdueDays > 0)
                                <span class="font-medium text-danger-600 dark:text-danger-400">{{ $row['due_date']->format('M j') }} · {{ $overdueDays }}d overdue</span>
                            @elseif ($row['due_date']->isToday())
                                <span class="font-medium text-warning-600 dark:text-warning-400">Today</span>
                            @else
                                <span class="text-gray-700 dark:text-gray-300">{{ $row['due_date']->format('M j') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-3.5 pe-6 text-end">
                            <a
                                href="{{ $row['module'] === 'RFP' ? RfpApprovalResource::getUrl('index') : ApprovalResource::getUrl('index') }}"
                                class="inline-flex rounded-[10px] bg-primary-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700"
                            >Process</a>
                        </td>
                    </tr>
                @endforeach
                <tr x-show="! rows.some((row, i) => visible(i))" x-cloak>
                    <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-400">Nothing here. Try another tab or clear the filter.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
        <span class="tabular-nums">Showing the <strong class="font-semibold text-gray-800 dark:text-gray-200">{{ count($queue) }}</strong> most urgent of {{ $queueTotal }} items</span>
        <span class="flex gap-4">
            <a href="{{ RfpApprovalResource::getUrl('index') }}" class="font-semibold text-primary-600 hover:text-primary-800 dark:text-primary-400">All RFP approvals</a>
            <a href="{{ ApprovalResource::getUrl('index') }}" class="font-semibold text-primary-600 hover:text-primary-800 dark:text-primary-400">All P2P approvals</a>
        </span>
    </div>
</section>
