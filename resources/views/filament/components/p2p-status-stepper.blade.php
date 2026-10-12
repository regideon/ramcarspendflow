@props([
    'status' => null,
    'histories' => null,
    'escalations' => null,
])

@php
    use App\Enums\P2pStatus;
    use App\Models\P2p\Escalation;

    $current = $status instanceof P2pStatus ? $status : P2pStatus::tryFrom((int) $status);

    // The canonical forward path on the invoice (before any escalation detour).
    $flow = [
        ['key' => 'vendor', 'label' => 'Vendor'],
        ['key' => 'processor', 'label' => 'Processor'],
        ['key' => 'checker', 'label' => 'Checker'],
        ['key' => 'sap', 'label' => 'SAP'],
        ['key' => 'completed', 'label' => 'Completed'],
    ];

    // Which flow step each status belongs to; used to place the "current" marker.
    $stepForStatus = fn (?P2pStatus $status): ?string => match ($status) {
        P2pStatus::Pending, P2pStatus::ReturnedToVendor => 'vendor',
        P2pStatus::SubmittedToAp, P2pStatus::ReturnedToApProcessor => 'processor',
        P2pStatus::SubmittedToChecker, P2pStatus::CheckerReview, P2pStatus::Rejected, P2pStatus::RejectedLegacy => 'checker',
        P2pStatus::InvoiceParked, P2pStatus::InvoicePosted, P2pStatus::PendingPost => 'sap',
        P2pStatus::Completed => 'completed',
        default => null,
    };

    // Statuses that are a dead end rather than a position on the path.
    $isTerminal = $current !== null && in_array($current, [
        P2pStatus::Rejected,
        P2pStatus::RejectedLegacy,
        P2pStatus::Cancelled,
        P2pStatus::Reversed,
    ], true);

    // How far along the path this invoice has actually reached. History is the source
    // of truth so back-and-forth (return -> resubmit) still lights the visited steps.
    $flowKeys = array_column($flow, 'key');

    $furthestIndex = 0;
    foreach (($histories ?? []) as $history) {
        $key = $stepForStatus($history->to_status);
        if ($key !== null) {
            $furthestIndex = max($furthestIndex, array_search($key, $flowKeys, true));
        }
    }

    $currentKey = $stepForStatus($current);
    $currentIndex = $currentKey !== null ? array_search($currentKey, $flowKeys, true) : false;

    // A returned-to-vendor invoice sits back at Vendor even though it went further before.
    if ($currentIndex !== false) {
        $furthestIndex = max($furthestIndex, $currentIndex);
    }

    $pillColor = $current?->getColor() ?? 'gray';
    $pillClasses = match ($pillColor) {
        'danger' => 'bg-[#FBE3E5] text-[#a51f2a] dark:bg-red-500/10 dark:text-red-400',
        'warning' => 'bg-[#FBF0D6] text-[#8a6410] dark:bg-amber-500/10 dark:text-amber-400',
        'success' => 'bg-[#E3F5EA] text-[#1E7A47] dark:bg-green-500/10 dark:text-green-400',
        'info' => 'bg-[#E5ECFA] text-[#274a8f] dark:bg-blue-500/10 dark:text-blue-400',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-500/10 dark:text-gray-400',
    };

    // Open escalations, grouped into batches (one stepper per GR/batch).
    $escalationList = collect($escalations ?? []);
    $escalationSteps = fn (Escalation $escalation): array => $escalation->reason?->steps
        ? $escalation->reason->steps->sortBy('step_order')->values()->map(fn ($step): array => [
            'label' => Escalation::roleLabel($step->role),
            'order' => $step->step_order,
        ])->all()
        : [];
@endphp

<div {{ $attributes->merge([
    'class' => 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10',
]) }}>
    <div class="mb-3 flex items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-[#5a647a] dark:text-gray-400">Workflow Stage</p>

        @if ($current)
            <span class="inline-block rounded-full px-2.5 py-1 text-[11px] font-bold leading-none {{ $pillClasses }}">
                {{ $current->getLabel() }}
            </span>
        @endif
    </div>

    {{-- The forward path. Hidden while the request is out on an escalation, so the
         escalation route(s) below become the primary flow. Dead-end statuses
         (rejected/cancelled/reversed) bolt an extra node on the end so the trail
         still shows where it stopped. --}}
    @unless ($escalationList->isNotEmpty())
    <div class="flex items-start">
        @foreach ($flow as $index => $step)
            @php
                $isDone = $index < $furthestIndex;
                $isCurrent = ! $isTerminal && $index === $furthestIndex;
            @endphp

            <div class="{{ $loop->last ? 'flex-none' : 'flex-1' }}">
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold {{ $isCurrent ? 'border-[#1E7A47] bg-[#1E7A47] text-white dark:border-green-500 dark:bg-green-500' : ($isDone ? 'border-[#38A169] bg-[#E3F5EA] text-[#1E7A47] dark:border-green-500/60 dark:bg-green-500/10 dark:text-green-400' : 'border-[#D3DAE6] bg-white text-[#94A3B8] dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400') }}">
                            @if ($isDone)
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.5 7.5a1 1 0 0 1-1.414 0l-3.5-3.5a1 1 0 0 1 1.414-1.414l2.793 2.793 6.793-6.793a1 1 0 0 1 1.414-.006z" clip-rule="evenodd" />
                                </svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </div>

                        <div class="mt-1.5 pr-1">
                            <span class="text-[11px] font-semibold leading-tight {{ $isCurrent ? 'text-[#1A2540] dark:text-white' : ($isDone ? 'text-[#1E7A47] dark:text-green-400' : 'text-[#94A3B8] dark:text-gray-500') }}">
                                {{ $step['label'] }}
                            </span>
                        </div>
                    </div>

                    @unless ($loop->last)
                        <div class="mx-2 h-0.5 flex-1 rounded {{ $isDone ? 'bg-[#38A169] dark:bg-green-500/60' : 'bg-[#D3DAE6] dark:bg-gray-700' }}"></div>
                    @endunless
                </div>
            </div>
        @endforeach

        @if ($isTerminal)
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="mx-2 h-0.5 w-6 rounded bg-[#D3DAE6] dark:bg-gray-700"></div>
                    <div class="flex flex-col items-center">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold {{ $pillClasses }}">
                            !
                        </div>
                        <div class="mt-1.5 pr-1">
                            <span class="text-[11px] font-semibold leading-tight {{ $pillClasses }}">
                                {{ $current->getLabel() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endunless

    {{-- Escalated crossings: one stepper per GR/batch, showing the configured hops. --}}
    @foreach ($escalationList as $escalation)
        @php
            $hops = $escalationSteps($escalation);
            $currentOrder = (int) $escalation->current_step_order;
            $resolved = $escalation->status === Escalation::STATUS_RESOLVED;
        @endphp

        <div class="{{ $loop->first ? '' : 'mt-4 border-t border-gray-100 pt-3 dark:border-white/10' }}">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#5a647a] dark:text-gray-400">
                    Escalation · {{ $escalation->requestNumber() }}
                </p>
                <span class="inline-block rounded-full px-2.5 py-1 text-[11px] font-bold leading-none {{ $resolved ? 'bg-[#E3F5EA] text-[#1E7A47] dark:bg-green-500/10 dark:text-green-400' : 'bg-[#F5F3FF] text-[#6D28D9] dark:bg-purple-500/10 dark:text-purple-400' }}">
                    {{ $escalation->stepLabel() }}
                </span>
            </div>

            <div class="flex items-start">
                {{-- Origin node --}}
                <div class="flex-1">
                    <div class="flex items-center">
                        <div class="flex flex-col items-center">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-[#38A169] bg-[#E3F5EA] text-[#1E7A47] text-[11px] font-bold dark:border-green-500/60 dark:bg-green-500/10 dark:text-green-400">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.5 7.5a1 1 0 0 1-1.414 0l-3.5-3.5a1 1 0 0 1 1.414-1.414l2.793 2.793 6.793-6.793a1 1 0 0 1 1.414-.006z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="mt-1.5 pr-1">
                                <span class="text-[11px] font-semibold leading-tight text-[#1E7A47] dark:text-green-400">
                                    Escalated
                                </span>
                            </div>
                        </div>

                        @if ($hops !== [])
                            <div class="mx-2 h-0.5 flex-1 rounded bg-[#38A169] dark:bg-green-500/60"></div>
                        @endif
                    </div>
                </div>

                {{-- Route hops --}}
                @foreach ($hops as $hop)
                    @php
                        $isDone = $resolved || $hop['order'] < $currentOrder;
                        $isCurrent = ! $resolved && $hop['order'] === $currentOrder;
                    @endphp

                    <div class="{{ $loop->last ? 'flex-none' : 'flex-1' }}">
                        <div class="flex items-center">
                            <div class="flex flex-col items-center">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold {{ $isCurrent ? 'border-[#6D28D9] bg-[#6D28D9] text-white dark:border-purple-500 dark:bg-purple-500' : ($isDone ? 'border-[#38A169] bg-[#E3F5EA] text-[#1E7A47] dark:border-green-500/60 dark:bg-green-500/10 dark:text-green-400' : 'border-[#D3DAE6] bg-white text-[#94A3B8] dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400') }}">
                                    @if ($isDone)
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.5 7.5a1 1 0 0 1-1.414 0l-3.5-3.5a1 1 0 0 1 1.414-1.414l2.793 2.793 6.793-6.793a1 1 0 0 1 1.414-.006z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </div>

                                <div class="mt-1.5 pr-1">
                                    <span class="text-[11px] font-semibold leading-tight {{ $isCurrent ? 'text-[#5B21B6] dark:text-purple-300' : ($isDone ? 'text-[#1E7A47] dark:text-green-400' : 'text-[#94A3B8] dark:text-gray-500') }}">
                                        {{ $hop['label'] }}
                                    </span>
                                </div>
                            </div>

                            @unless ($loop->last)
                                <div class="mx-2 h-0.5 flex-1 rounded {{ $isDone ? 'bg-[#38A169] dark:bg-green-500/60' : 'bg-[#D3DAE6] dark:bg-gray-700' }}"></div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
