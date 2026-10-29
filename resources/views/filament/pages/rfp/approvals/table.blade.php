@php
    use App\Enums\RfpStatus;
    use App\Filament\Pages\Rfp\Approvals;
    use App\Filament\Resources\RfpApprovals\RfpApprovalReviewResource;
    use App\Filament\Resources\RfpApprovals\Tables\RfpApprovalsTable;

    $rfps = $this->rfps;
    $hasFilters = filled($search) || filled($focus) || count($this->activeFilters);

    $statusClasses = fn (RfpStatus $status): string => match ($status->getColor()) {
        'gray' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-500/15 dark:text-warning-300',
        'success' => 'bg-success-100 text-success-800 dark:bg-success-500/15 dark:text-success-300',
        'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300',
        default => 'bg-primary-50 text-primary-800 ring-1 ring-primary-200/60 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30',
    };
    $statusDot = fn (RfpStatus $status): string => match ($status->getColor()) {
        'gray' => 'bg-gray-400',
        'warning' => 'bg-warning-500',
        'success' => 'bg-success-500',
        'danger' => 'bg-danger-500',
        default => 'bg-primary-500',
    };

    $sortIcon = fn (string $column): string => $sort === $column ? ($direction === 'asc' ? 'heroicon-m-arrow-up' : 'heroicon-m-arrow-down') : 'heroicon-m-arrows-up-down';

    // [column key for the Columns menu, label, sort column, alignment]
    $headers = [
        [null, 'RFP ID', 'rfp_number', 'text-start'],
        ['due', 'Due', 'due_date', 'text-start'],
        ['urgency', 'Urgency', null, 'text-start'],
        ['company', 'Company', null, 'text-start'],
        ['payee', 'Payee', null, 'text-start'],
        ['vendors', 'Vendors', null, 'text-start'],
        ['lines', 'Lines', null, 'text-end'],
        ['amount', 'Total Amount', 'total_php_amount', 'text-end'],
        ['requestor', 'Requestor', null, 'text-start'],
        ['status', 'Status', 'status', 'text-start'],
        ['approver', 'Current Approver', null, 'text-start'],
        ['created', 'Created Date', 'created_at', 'text-start'],
        ['updated', 'Last Update', 'updated_at', 'text-start'],
    ];
@endphp

<div data-tour="table" class="relative w-full overflow-x-auto">
    <div wire:loading.delay.class.remove="opacity-0" wire:target="setTab,pickFocus,search,company,vendor,dueType,type,statuses,from,until,removeFilter,clearFilters,sortBy,gotoPage,nextPage,previousPage,perPage" class="pointer-events-none absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden opacity-0 transition">
        <div class="h-full w-1/3 animate-pulse bg-primary-500"></div>
    </div>

    <table class="w-full min-w-[80rem] border-collapse text-start text-xs">
        <thead>
            <tr class="bg-gray-50/80 text-[0.6875rem] font-semibold text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                @foreach ($headers as [$key, $label, $sortColumn, $align])
                    <th @if ($key) x-show="shows('{{ $key }}')" @endif @class(['whitespace-nowrap px-3 py-3', $align, 'ps-4' => $loop->first])>
                        @if ($sortColumn)
                            <button type="button" wire:click="sortBy('{{ $sortColumn }}')" @class(['inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white', 'flex-row-reverse' => $align === 'text-end'])>
                                {{ $label }}
                                <x-filament::icon :icon="$sortIcon($sortColumn)" @class(['size-3.5', 'text-primary-600' => $sort === $sortColumn, 'opacity-40' => $sort !== $sortColumn]) />
                            </button>
                        @else
                            {{ $label }}
                        @endif
                    </th>
                @endforeach
                <th class="px-4 py-3 text-end">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
            @forelse ($rfps as $rfp)
                @php
                    $url = RfpApprovalReviewResource::getUrl('view', ['record' => $rfp]);
                    $dueState = $rfp->dueState();
                    $vendors = RfpApprovalsTable::itemVendorNames($rfp);
                    $foreign = collect($rfp->foreignTotals())->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))->implode(' · ');
                    $approver = $rfp->currentApproverLabel();
                @endphp
                <tr
                    wire:key="rfp-{{ $rfp->id }}"
                    x-on:click="window.location.href = @js($url)"
                    class="group cursor-pointer transition even:bg-gray-50/40 hover:bg-primary-50/40 dark:even:bg-white/[0.02] dark:hover:bg-white/5"
                >
                    <td class="whitespace-nowrap px-3 py-3.5 ps-4">
                        <a href="{{ $url }}" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300">{{ $rfp->rfp_number }}</a>
                    </td>
                    <td x-show="shows('due')" class="whitespace-nowrap px-3 py-3.5">
                        @if ($dueState === 'overdue')
                            <div class="flex items-center gap-1 font-medium text-danger-600 dark:text-danger-400">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-3.5" />
                                {{ $rfp->due_date->format('M j, Y') }}
                            </div>
                            <div class="text-[0.6875rem] font-semibold text-danger-600 dark:text-danger-400">Overdue by {{ trans_choice('{1} :count day|[2,*] :count days', (int) $rfp->due_date->diffInDays(today())) }}</div>
                        @elseif ($dueState === 'today')
                            <div class="flex items-center gap-1 font-medium text-warning-600 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-clock" class="size-3.5" />
                                {{ $rfp->due_date->format('M j, Y') }}
                            </div>
                            <div class="text-[0.6875rem] font-semibold text-warning-600 dark:text-warning-400">Due today</div>
                        @else
                            <span class="font-medium text-gray-600 dark:text-gray-300">{{ $rfp->due_date?->format('M j, Y') ?? '—' }}</span>
                        @endif
                    </td>
                    <td x-show="shows('urgency')" class="whitespace-nowrap px-3 py-3.5">
                        @if ($rfp->dueType?->code === 'urgent')
                            <span class="inline-flex items-center gap-1 rounded-full bg-danger-100 px-2 py-0.5 text-[0.6875rem] font-semibold text-danger-700 dark:bg-danger-500/15 dark:text-danger-300">
                                <span class="size-1.5 rounded-full bg-danger-500"></span>{{ $rfp->dueType->name }}
                            </span>
                        @elseif ($rfp->dueType)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[0.6875rem] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $rfp->dueType->name }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td x-show="shows('company')" class="whitespace-nowrap px-3 py-3.5 font-medium text-gray-600 dark:text-gray-300">{{ $rfp->company?->short_name ?? '—' }}</td>
                    <td x-show="shows('payee')" class="px-3 py-3.5">
                        <span class="block max-w-48 truncate font-medium" title="{{ $rfp->vendor?->name }}">{{ $rfp->vendor?->name ?? '—' }}</span>
                    </td>
                    <td x-show="shows('vendors')" class="px-3 py-3.5 text-gray-600 dark:text-gray-300" x-data="{ more: false }">
                        @forelse ($vendors as $name)
                            <div @if ($loop->index >= 2) x-cloak x-show="more" @endif class="max-w-52 truncate leading-tight" title="{{ $name }}">{{ $name }}</div>
                        @empty
                            <span class="text-gray-400">—</span>
                        @endforelse
                        @if (count($vendors) > 2)
                            <button type="button" x-on:click.stop="more = ! more" class="text-[0.6875rem] font-medium text-primary-600 hover:underline dark:text-primary-400" x-text="more ? 'Show less' : @js('+'.(count($vendors) - 2).' more')"></button>
                        @endif
                    </td>
                    <td x-show="shows('lines')" class="whitespace-nowrap px-3 py-3.5 text-end font-medium text-gray-600 dark:text-gray-300">{{ $rfp->items_count }} {{ str('line')->plural($rfp->items_count) }}</td>
                    <td x-show="shows('amount')" class="whitespace-nowrap px-3 py-3.5 text-end">
                        <div class="font-display text-sm font-bold tabular-nums tracking-tight">PHP {{ number_format((float) $rfp->total_php_amount, 2) }}</div>
                        @if ($foreign)
                            <div class="text-[0.6875rem] tabular-nums text-gray-500 dark:text-gray-400">{{ $foreign }}</div>
                        @endif
                    </td>
                    <td x-show="shows('requestor')" class="whitespace-nowrap px-3 py-3.5 text-gray-600 dark:text-gray-300">{{ $rfp->requestor?->name ?? '—' }}</td>
                    <td x-show="shows('status')" class="whitespace-nowrap px-3 py-3.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[0.6875rem] font-semibold {{ $statusClasses($rfp->status) }}">
                            <span class="size-1.5 rounded-full {{ $statusDot($rfp->status) }}"></span>
                            {{ $rfp->status->getLabel() }}
                        </span>
                    </td>
                    <td x-show="shows('approver')" class="whitespace-nowrap px-3 py-3.5">
                        @if ($approver)
                            <span class="inline-flex rounded-md bg-danger-50 px-2 py-0.5 text-[0.6875rem] font-semibold text-danger-700 ring-1 ring-danger-200/60 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/30">{{ $approver }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td x-show="shows('created')" class="whitespace-nowrap px-3 py-3.5 text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $rfp->created_at?->format('M j, Y H:i') }}</td>
                    <td x-show="shows('updated')" class="whitespace-nowrap px-3 py-3.5 text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $rfp->updated_at?->format('M j, Y H:i') }}</td>
                    <td class="whitespace-nowrap px-4 py-3.5 text-end" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            <button
                                type="button"
                                title="Messages"
                                x-on:click="$dispatch('open-comments-thread', { type: @js($rfp->getMorphClass()), id: {{ $rfp->id }} })"
                                class="relative rounded p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/10 dark:hover:text-white"
                            >
                                <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="size-[1.125rem]" />
                                @if ($rfp->unread_comments_count)
                                    <span class="absolute -end-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-danger-600 px-1 text-[0.625rem] font-bold leading-4 text-white ring-2 ring-white dark:ring-gray-900">{{ $rfp->unread_comments_count }}</span>
                                    <span class="sr-only">{{ $rfp->unread_comments_count }} unread</span>
                                @endif
                            </button>
                            <a href="{{ $url }}" title="View" class="rounded p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/10 dark:hover:text-white">
                                <x-filament::icon icon="heroicon-o-eye" class="size-[1.125rem]" />
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) + 1 }}" class="px-6 py-16 text-center">
                        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="mx-auto size-10 text-gray-300" />
                        <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ $tab === 'mine' && ! $hasFilters ? 'Nothing waiting for you' : 'No RFPs here' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            @if ($hasFilters)
                                Try another tab or <button type="button" wire:click="clearFilters" class="font-semibold text-primary-600 hover:underline">clear the filters</button>.
                            @else
                                Try another tab.
                            @endif
                        </p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
@if ($rfps->total())
    <div class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 px-4 py-3.5 text-xs text-gray-500 sm:flex-row dark:border-white/5 dark:text-gray-400">
        <div class="flex items-center gap-4">
            <span class="tabular-nums">Showing <strong class="text-gray-900 dark:text-white">{{ $rfps->firstItem() }}</strong> to <strong class="text-gray-900 dark:text-white">{{ $rfps->lastItem() }}</strong> of <strong class="text-gray-900 dark:text-white">{{ number_format($rfps->total()) }}</strong> results</span>
            <label class="flex items-center gap-1.5">
                Rows:
                <select wire:model.live="perPage" class="rounded-lg border-0 bg-gray-50 py-1 ps-2.5 pe-7 text-xs font-medium text-gray-900 ring-1 ring-gray-200 dark:bg-white/5 dark:text-white dark:ring-white/10">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}">{{ $size }} per page</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($rfps->hasPages())
            @php
                $current = $rfps->currentPage();
                $last = $rfps->lastPage();
                $pages = collect([1, $last, ...range(max(1, $current - 1), min($last, $current + 1))])->unique()->sort()->values();
            @endphp
            <nav class="flex items-center gap-1" aria-label="Pagination">
                <button type="button" wire:click="previousPage" @disabled($rfps->onFirstPage()) class="rounded-md bg-gray-50 px-2.5 py-1.5 font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:bg-white/5 dark:text-gray-300">Previous</button>
                @foreach ($pages as $page)
                    @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                        <span class="w-6 text-center">…</span>
                    @endif
                    <button
                        type="button"
                        wire:click="gotoPage({{ $page }})"
                        @class([
                            'flex size-8 items-center justify-center rounded-md tabular-nums',
                            'bg-primary-600 font-bold text-white shadow-sm' => $page === $current,
                            'font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $page !== $current,
                        ])
                    >{{ $page }}</button>
                @endforeach
                <button type="button" wire:click="nextPage" @disabled(! $rfps->hasMorePages()) class="rounded-md bg-gray-50 px-2.5 py-1.5 font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:bg-white/5 dark:text-gray-300">Next</button>
            </nav>
        @endif
    </div>
@endif
