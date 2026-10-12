@php
    use App\Filament\Resources\P2p\Approvals\ApprovalResource;
    use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
    use App\Services\Accounting\ApProcessorDashboard;
    use App\Support\Peso;

    $rows = collect($rows);

    $tabs = ['all' => ['All', $rows->count()]];
    foreach (ApProcessorDashboard::TABS as $key => $label) {
        $tabs[$key] = [$label, $rows->filter(fn (array $row): bool => in_array($key, $row['tabs'], true))->count()];
    }

    // Queue age: 0–2d gray, 3–5d info, 6–10d warning, 11+d danger.
    $agingColor = fn (int $days): string => match (true) {
        $days <= 2 => 'gray',
        $days <= 5 => 'info',
        $days <= 10 => 'warning',
        default => 'danger',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="My work queue"
        description="RFP and P2P items waiting on you, most urgent first"
        x-data="{ tab: 'all', module: 'all', search: '' }"
    >
        <x-filament::tabs class="mb-4" contained>
            @foreach ($tabs as $key => [$label, $count])
                <x-filament::tabs.item :alpine-active="'tab === \'' . $key . '\''" x-on:click="tab = '{{ $key }}'" :badge="$count">
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="sm:w-80">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" x-model.debounce.200ms="search" placeholder="Search reference, requestor, vendor, PO…" />
                </x-filament::input.wrapper>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Module</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select x-model="module">
                        <option value="all">All</option>
                        <option value="RFP">RFP</option>
                        <option value="P2P">P2P</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="-mx-4 overflow-x-auto sm:-mx-6">
            <table class="w-full min-w-[72rem] divide-y divide-gray-200 text-sm dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 text-start sm:ps-6">Module</th>
                        <th class="px-3 py-3 text-start">Reference</th>
                        <th class="px-3 py-3 text-start">Task</th>
                        <th class="px-3 py-3 text-start">Requestor / Vendor</th>
                        <th class="px-3 py-3 text-end">Amount</th>
                        <th class="px-3 py-3 text-start">Checks</th>
                        <th class="px-3 py-3 text-start">Due date</th>
                        <th class="px-3 py-3 text-center">In my queue</th>
                        <th class="px-4 py-3 text-end sm:pe-6"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($rows as $row)
                        @php
                            $overdueDays = $row['due_date']->lt(today()) ? (int) $row['due_date']->diffInDays(today()) : 0;
                            $haystack = str(implode(' ', [$row['reference'], $row['sub'], $row['party'], $row['company'], $row['description']]))->lower();
                            $isRework = $row['task'] === ApProcessorDashboard::TASK_REWORK;
                        @endphp
                        <tr
                            x-show="(tab === 'all' || @js($row['tabs']).includes(tab)) && (module === 'all' || module === @js($row['module'])) && (search === '' || @js((string) $haystack).includes(search.toLowerCase()))"
                            @class(['hover:bg-gray-50 dark:hover:bg-white/5', 'bg-danger-50/40 dark:bg-danger-500/5' => $overdueDays > 0])
                        >
                            <td class="px-4 py-3 sm:ps-6">@include('filament.widgets.accounting.ap-processor._module', ['module' => $row['module']])</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <div class="font-semibold text-gray-950 dark:text-white">{{ $row['reference'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['sub'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span @class([
                                    'inline-flex items-center gap-1',
                                    'font-semibold text-warning-700 dark:text-warning-400' => $isRework,
                                    'font-semibold text-info-700 dark:text-info-400' => $row['task'] === ApProcessorDashboard::TASK_ESCALATION,
                                    'text-gray-950 dark:text-white' => ! $isRework && $row['task'] !== ApProcessorDashboard::TASK_ESCALATION,
                                ])>
                                    @if ($isRework)
                                        <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="h-4 w-4" />
                                    @elseif ($row['task'] === ApProcessorDashboard::TASK_ESCALATION)
                                        <x-filament::icon icon="heroicon-m-check-badge" class="h-4 w-4" />
                                    @endif
                                    {{ $row['task'] }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="max-w-44 truncate font-medium text-gray-950 dark:text-white">{{ $row['party'] }}</span>
                                    <x-filament::badge color="gray" size="sm">{{ $row['company'] }}</x-filament::badge>
                                </div>
                                <div class="max-w-56 truncate text-xs text-gray-500">{{ $row['description'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-end">
                                <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($row['amount']) }}</div>
                                @if ($row['foreign'])
                                    <div class="text-xs tabular-nums text-gray-500">{{ $row['foreign'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($row['match'])
                                        <x-filament::badge :color="$row['match']->getColor()" size="sm" :tooltip="$row['match']->description()">{{ $row['match']->getLabel() }}</x-filament::badge>
                                    @endif
                                    @foreach ($row['checks'] as [$label, $color])
                                        <x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge>
                                    @endforeach
                                </div>
                                @if ($row['ai_read'])
                                    <div @class(['mt-1 text-xs', 'text-danger-600 dark:text-danger-400' => $row['match']?->value === 'no_match', 'text-gray-500' => $row['match']?->value !== 'no_match'])>{{ $row['ai_read'] }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($overdueDays > 0)
                                    <span class="inline-flex items-center gap-1.5 font-medium text-danger-600 dark:text-danger-400">
                                        <span class="h-2 w-2 rounded-full bg-danger-500"></span>
                                        {{ $row['due_date']->format('M j') }}
                                        <x-filament::badge color="danger" size="sm">{{ $overdueDays }}d overdue</x-filament::badge>
                                    </span>
                                @elseif ($row['due_date']->isToday())
                                    <span class="font-medium text-warning-600 dark:text-warning-400">Today</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">{{ $row['due_date']->format('M j') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <x-filament::badge :color="$agingColor($row['age'])" size="sm" class="inline-flex">{{ $row['age'] }}d</x-filament::badge>
                            </td>
                            <td class="px-4 py-3 text-end sm:pe-6">
                                @if ($row['module'] === 'RFP')
                                    <x-filament::button tag="a" :href="RfpApprovalResource::getUrl('index')" size="xs" color="gray">Review</x-filament::button>
                                @else
                                    <x-filament::button tag="a" :href="ApprovalResource::getUrl('index')" size="xs">Process</x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-slot name="footer">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>Showing the {{ $rows->count() }} most urgent of {{ $total }} items.</span>
                <span class="flex gap-3">
                    <x-filament::link :href="RfpApprovalResource::getUrl('index')" size="sm">All RFP approvals</x-filament::link>
                    <x-filament::link :href="ApprovalResource::getUrl('index')" size="sm">All P2P approvals</x-filament::link>
                </span>
            </div>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
