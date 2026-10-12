@php
    use App\Enums\RfpStatus;
    use App\Services\Rfp\RfpRequestorDashboard;
    use App\Support\Peso;

    $tabOf = fn (RfpStatus $status): string => match (true) {
        $status === RfpStatus::Draft => 'drafts',
        $status === RfpStatus::ForRevision => 'revision',
        $status === RfpStatus::Completed => 'completed',
        in_array($status, [RfpStatus::Rejected, RfpStatus::Cancelled], true) => 'closed',
        default => 'progress',
    };

    $rows = collect($rows)->map(fn (array $row): array => [...$row, 'tab' => $tabOf($row['status'])]);

    $tabs = [
        'all' => ['All', $rows->count()],
        'progress' => ['In progress', $rows->where('tab', 'progress')->count()],
        'revision' => ['For revision', $rows->where('tab', 'revision')->count()],
        'drafts' => ['Drafts', $rows->where('tab', 'drafts')->count()],
        'completed' => ['Completed', $rows->where('tab', 'completed')->count()],
        'closed' => ['Rejected / cancelled', $rows->where('tab', 'closed')->count()],
    ];

    // Aging chip: 0–2d gray, 3–5d info, 6–10d warning, 11+d danger.
    $agingColor = fn (int $days): string => match (true) {
        $days <= 2 => 'gray',
        $days <= 5 => 'info',
        $days <= 10 => 'warning',
        default => 'danger',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="My requests in flight"
        description="Where each request is on its route and who has it now"
        x-data="{ tab: 'progress' }"
    >
        <x-filament::tabs class="mb-4" contained>
            @foreach ($tabs as $key => [$label, $count])
                <x-filament::tabs.item
                    :alpine-active="'tab === \'' . $key . '\''"
                    x-on:click="tab = '{{ $key }}'"
                    :badge="$count"
                >
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="-mx-4 overflow-x-auto sm:-mx-6">
            <table class="w-full min-w-[64rem] divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 text-start sm:ps-6">RFP no.</th>
                        <th class="px-3 py-3 text-start">Type</th>
                        <th class="px-3 py-3 text-start">Payee</th>
                        <th class="px-3 py-3 text-start">Purpose</th>
                        <th class="px-3 py-3 text-end">Amount</th>
                        <th class="px-3 py-3 text-center">Progress</th>
                        <th class="px-3 py-3 text-start">Currently with</th>
                        <th class="px-3 py-3 text-center">At this step</th>
                        <th class="px-3 py-3 text-start">Due date</th>
                        <th class="px-4 py-3 text-end sm:pe-6">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($rows as $row)
                        @php
                            $status = $row['status'];
                            $stage = RfpRequestorDashboard::stageOf($status);
                            $isFinal = in_array($status, RfpStatus::final(), true);
                            $overdueDays = ! $isFinal && $row['due_date']->isPast() ? (int) $row['due_date']->diffInDays(today()) : 0;
                        @endphp
                        <tr
                            x-show="tab === 'all' || tab === '{{ $row['tab'] }}'"
                            @if ($row['tab'] !== 'progress') x-cloak @endif
                            @class(['hover:bg-gray-50 dark:hover:bg-white/5', 'bg-danger-50/40 dark:bg-danger-500/5' => $overdueDays > 0])
                        >
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-primary-600 sm:ps-6 dark:text-primary-400">{{ $row['rfp_number'] }}</td>
                            <td class="px-3 py-3">
                                <x-filament::badge :color="$row['type'] === App\Enums\RfpRequestType::Reimbursement ? 'info' : 'gray'" size="sm">
                                    {{ $row['type']->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-950 dark:text-white">{{ $row['payee'] }}</td>
                            <td class="max-w-56 truncate px-3 py-3 text-gray-500 dark:text-gray-400" title="{{ $row['purpose'] }}">{{ $row['purpose'] }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-end">
                                <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($row['amount']) }}</div>
                                @if ($row['foreign'])
                                    <div class="text-xs tabular-nums text-gray-500">{{ $row['foreign'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-center" title="Step {{ $stage }} of 7: {{ RfpRequestorDashboard::STAGES[$stage] }}">
                                    @foreach (RfpRequestorDashboard::STAGES as $number => $label)
                                        @php
                                            $state = match (true) {
                                                $number < $stage, $status === RfpStatus::Completed => 'done',
                                                $number === $stage => 'current',
                                                default => 'upcoming',
                                            };
                                        @endphp
                                        @unless ($loop->first)
                                            <span @class(['h-0.5 w-3', 'bg-success-500' => $number <= $stage, 'bg-gray-200 dark:bg-white/10' => $number > $stage])></span>
                                        @endunless
                                        <span
                                            @class([
                                                'grid shrink-0 place-items-center rounded-full text-[9px] font-bold text-white',
                                                'h-3.5 w-3.5 bg-success-500' => $state === 'done',
                                                'h-4 w-4 ring-2' => $state === 'current',
                                                'bg-info-500 ring-info-200 dark:ring-info-500/30' => $state === 'current' && $status->isAwaitingApproval(),
                                                'bg-warning-500 ring-warning-200 dark:ring-warning-500/30' => $state === 'current' && $status === RfpStatus::ForRevision,
                                                'bg-gray-400 ring-gray-200 dark:ring-white/10' => $state === 'current' && $status === RfpStatus::Draft,
                                                'bg-danger-500 ring-danger-200 dark:ring-danger-500/30' => $state === 'current' && in_array($status, [RfpStatus::Rejected, RfpStatus::Cancelled], true),
                                                'h-2.5 w-2.5 bg-gray-200 dark:bg-white/10' => $state === 'upcoming',
                                            ])
                                        >
                                            @if ($state === 'done')
                                                ✓
                                            @elseif ($state === 'current')
                                                {{ $number }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-700 dark:text-gray-300">
                                @if ($row['with'])
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full bg-info-500"></span>
                                        {{ $row['with'] }}
                                    </span>
                                @elseif ($isFinal)
                                    <span class="text-gray-500">—</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 font-medium text-warning-700 dark:text-warning-400">
                                        <span class="h-2 w-2 rounded-full bg-warning-500"></span>
                                        You
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                @unless ($isFinal)
                                    <x-filament::badge :color="$agingColor($row['days_at_step'])" size="sm" class="inline-flex">{{ $row['days_at_step'] }}d</x-filament::badge>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($overdueDays > 0)
                                    <span class="inline-flex items-center gap-1.5 font-medium text-danger-600 dark:text-danger-400">
                                        <span class="h-2 w-2 rounded-full bg-danger-500"></span>
                                        {{ $row['due_date']->format('M j, Y') }}
                                        <x-filament::badge color="danger" size="sm">{{ $overdueDays }}d overdue</x-filament::badge>
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">{{ $row['due_date']->format('M j, Y') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end sm:pe-6">
                                <x-filament::badge :color="$status->getColor()" class="inline-flex">{{ $status->getLabel() }}</x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($tabs as $key => [$label, $count])
                        @if ($count === 0)
                            <tr x-show="tab === '{{ $key }}'" x-cloak>
                                <td colspan="10" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No {{ str($label)->lower() }} requests.</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
