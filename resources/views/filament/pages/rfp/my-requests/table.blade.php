@php
    use App\Enums\RfpStatus;
    use App\Filament\Pages\Rfp\MyRequests;
    use App\Filament\Resources\Rfps\RfpResource;
    use App\Support\Peso;

    $rfps = $this->rfps;
    $pageIds = $rfps->pluck('id')->map(fn ($id): string => (string) $id)->all();
    $allOnPageSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
    $hasFilters = filled($search) || filled($status) || filled($type) || filled($dueType) || filled($company) || filled($focus);
    $options = $this->filterOptions;

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

    $sortIcon = fn (string $column): ?string => $sort === $column ? ($direction === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down') : null;
    $pillClasses = 'rounded-xl border-0 bg-gray-50 py-1.5 ps-3 pe-8 text-xs font-medium text-gray-800 ring-1 ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10';
@endphp

<section
    class="sf-card flex flex-col overflow-hidden"
    x-data="{
        dense: (() => { try { return localStorage.getItem('sf.myRequests.dense') === '1' } catch (e) { return false } })(),
        toggleDense() {
            this.dense = ! this.dense
            try { localStorage.setItem('sf.myRequests.dense', this.dense ? '1' : '0') } catch (e) {}
        },
    }"
>
    <div data-tour="tabs">
        {{-- Saved views --}}
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50/60 px-6 pt-2 dark:border-white/5 dark:bg-white/[0.02]">
            <div class="-mb-px flex items-center gap-1 overflow-x-auto" role="tablist">
                @foreach (MyRequests::TABS as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'flex items-center gap-2 whitespace-nowrap border-b-2 px-3 pb-3 pt-2 text-sm transition',
                            'border-primary-600 font-bold text-primary-700 dark:text-primary-300' => $tab === $key,
                            'border-transparent font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $tab !== $key,
                        ])
                    >
                        {{ $label }}
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold tabular-nums',
                            'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => $tab === $key,
                            'bg-warning-100 text-warning-800 dark:bg-warning-500/15 dark:text-warning-300' => $tab !== $key && $key === 'for_revision' && $this->tabCounts[$key] > 0,
                            'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $tab !== $key && ! ($key === 'for_revision' && $this->tabCounts[$key] > 0),
                        ])>{{ number_format($this->tabCounts[$key]) }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Filters and table controls --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-3.5 dark:border-white/5">
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="status" aria-label="Status" class="{{ $pillClasses }}">
                    <option value="">Status</option>
                    @foreach ($options['statuses'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="type" aria-label="Type" class="{{ $pillClasses }}">
                    <option value="">Type</option>
                    @foreach ($options['types'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="dueType" aria-label="Due type" class="{{ $pillClasses }}">
                    <option value="">Due type</option>
                    @foreach ($options['dueTypes'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="company" aria-label="Company" class="{{ $pillClasses }}">
                    <option value="">Company</option>
                    @foreach ($options['companies'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="px-2 py-1 text-xs font-semibold text-gray-500 hover:text-danger-600">Clear all</button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    x-on:click="toggleDense()"
                    x-bind:title="dense ? 'Comfortable rows' : 'Compact rows'"
                    x-bind:class="dense ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/5'"
                    class="rounded-xl p-2 transition"
                >
                    <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
                </button>
                <button
                    type="button"
                    wire:click="export"
                    class="flex items-center gap-2 rounded-xl bg-gray-50 px-3.5 py-1.5 text-xs font-semibold text-gray-800 ring-1 ring-gray-200 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10"
                >
                    <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4" />
                    Export CSV
                </button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div data-tour="table" class="relative w-full overflow-x-auto">
        <div wire:loading.delay.class.remove="opacity-0" wire:target="setTab,pickFocus,search,status,type,dueType,company,sortBy,gotoPage,nextPage,previousPage,perPage,clearFilters" class="pointer-events-none absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden opacity-0 transition">
            <div class="h-full w-1/3 animate-pulse bg-primary-500"></div>
        </div>

        <table class="w-full min-w-[84rem] border-collapse text-start text-xs" x-bind:class="dense && 'sf-table-dense'">
            <thead>
                <tr class="bg-gray-50/80 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                    <th class="w-10 px-4 py-3 text-center">
                        <input
                            type="checkbox"
                            aria-label="Select all on this page"
                            @checked($allOnPageSelected)
                            wire:click="togglePage(@js($pageIds))"
                            class="size-4 cursor-pointer rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                    </th>
                    @foreach ([['rfp_number', 'RFP / Invoice no.', 'text-start'], ['status', 'Status', 'text-start'], ['track', 'Workflow track', 'text-start'], ['company', 'Company', 'text-start'], ['type', 'Type', 'text-start'], ['due_type', 'Priority', 'text-start'], ['payee', 'Payee', 'text-start'], ['total_php_amount', 'Amount (PHP)', 'text-end'], ['due_date', 'Due', 'text-start'], ['updated_at', 'Last activity', 'text-start']] as [$column, $label, $align])
                        <th class="whitespace-nowrap px-3 py-3 {{ $align }}">
                            @if (in_array($column, MyRequests::SORTABLE, true))
                                <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-900 dark:hover:text-white">
                                    {{ $label }}
                                    @if ($icon = $sortIcon($column))
                                        <x-filament::icon :icon="$icon" class="size-3.5 text-primary-600" />
                                    @else
                                        <x-filament::icon icon="heroicon-m-chevron-up-down" class="size-3.5 opacity-40" />
                                    @endif
                                </button>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                    <th class="px-2 py-3 text-center"><x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" class="mx-auto size-4" /><span class="sr-only">Unread messages</span></th>
                    <th class="px-4 py-3 text-center"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                @forelse ($rfps as $rfp)
                    @php
                        $isSelected = in_array((string) $rfp->id, $selected, true);
                        $isCancelled = $rfp->status === RfpStatus::Cancelled;
                        $dueState = $rfp->dueState();
                        $waitingDays = (int) $rfp->updated_at?->diffInDays(now());
                        $foreign = collect($rfp->foreignTotals())->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))->implode(' · ');
                        $invoice = $rfp->items->first()?->invoice_number;
                    @endphp
                    <tr
                        wire:key="rfp-{{ $rfp->id }}"
                        wire:click="openPreview({{ $rfp->id }})"
                        @class([
                            'group cursor-pointer transition',
                            'bg-primary-50/50 hover:bg-primary-50 dark:bg-primary-500/5' => $isSelected || $previewId === $rfp->id,
                            'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isSelected && $previewId !== $rfp->id,
                            'opacity-60 hover:opacity-100' => $isCancelled,
                        ])
                    >
                        <td class="px-4 py-3 text-center" x-on:click.stop>
                            <input type="checkbox" value="{{ $rfp->id }}" wire:model.live="selected" aria-label="Select {{ $rfp->rfp_number }}" class="size-4 cursor-pointer rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">
                            <div @class(['font-mono font-bold', 'line-through' => $isCancelled])>{{ $rfp->rfp_number }}</div>
                            @if ($invoice)
                                <div class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $invoice }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-col items-start gap-1">
                                <span @class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold', $statusClasses($rfp->status), 'line-through' => $isCancelled])>
                                    <span @class(['size-1.5 rounded-full', $statusDot($rfp->status), 'animate-pulse' => $rfp->status->isAwaitingApproval()])></span>
                                    {{ $rfp->status->getLabel() }}
                                </span>
                                @if ($rfp->status->isAwaitingApproval() && $waitingDays >= 3)
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-primary-50 px-1.5 py-0.5 text-[0.6875rem] font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                        <x-filament::icon icon="heroicon-m-clock" class="size-3" />
                                        Waiting {{ $waitingDays }}d on {{ str($rfp->currentApproverLabel() ?? 'approver')->limit(18) }}
                                    </span>
                                @elseif ($rfp->status === RfpStatus::ForRevision)
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-warning-50 px-1.5 py-0.5 text-[0.6875rem] font-medium text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                                        <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="size-3" />
                                        Fix and resubmit
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            @if ($rfp->status === RfpStatus::Draft)
                                <span class="italic text-gray-400">Not submitted yet</span>
                            @else
                                <div class="flex items-center gap-1">
                                    @foreach ($this->track($rfp) as $i => $step)
                                        @if (! $loop->first)
                                            <span @class(['h-0.5 w-3 rounded', 'bg-success-500' => in_array($step['state'], ['done', 'skipped'], true), 'bg-primary-500' => $step['state'] === 'current', 'bg-warning-400' => $step['state'] === 'revision', 'bg-danger-400' => $step['state'] === 'failed', 'bg-gray-200 dark:bg-white/10' => $step['state'] === 'upcoming'])></span>
                                        @endif
                                        <span title="{{ $step['label'] }}" @class([
                                            'flex items-center justify-center rounded-full text-[0.5625rem] font-bold',
                                            'size-3.5 bg-success-600 text-white' => $step['state'] === 'done',
                                            'size-3.5 bg-gray-300 text-white dark:bg-gray-600' => $step['state'] === 'skipped',
                                            'size-3.5 bg-primary-600 text-white ring-2 ring-primary-500/30' => $step['state'] === 'current',
                                            'size-3.5 bg-warning-400 text-warning-950 ring-2 ring-warning-300' => $step['state'] === 'revision',
                                            'size-3.5 bg-danger-600 text-white' => $step['state'] === 'failed',
                                            'size-2.5 bg-gray-200 dark:bg-white/10' => $step['state'] === 'upcoming',
                                        ])>
                                            @switch($step['state'])
                                                @case('done') ✓ @break
                                                @case('skipped') » @break
                                                @case('current') {{ $i + 1 }} @break
                                                @case('revision') ! @break
                                                @case('failed') ✕ @break
                                            @endswitch
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-3"><span class="rounded-md bg-gray-100 px-2 py-0.5 text-[0.6875rem] font-semibold text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $rfp->company?->short_name ?: $rfp->company?->code ?? '—' }}</span></td>
                        <td class="whitespace-nowrap px-3 py-3 text-gray-600 dark:text-gray-300">{{ $rfp->request_type?->getLabel() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">
                            @if ($rfp->dueType?->code === 'urgent')
                                <span class="inline-flex items-center gap-1 rounded-full bg-danger-100 px-2 py-0.5 text-[0.6875rem] font-bold uppercase text-danger-700 dark:bg-danger-500/15 dark:text-danger-300">
                                    <span class="size-1.5 rounded-full bg-danger-500"></span>{{ $rfp->dueType->name }}
                                </span>
                            @else
                                <span class="text-gray-500 dark:text-gray-400">{{ $rfp->dueType?->name ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-3"><span class="block max-w-44 truncate font-semibold" title="{{ $rfp->vendor?->name }}">{{ $rfp->vendor?->name ?? '—' }}</span></td>
                        <td class="whitespace-nowrap px-3 py-3 text-end">
                            <div @class(['font-mono font-bold tabular-nums', 'text-success-700 dark:text-success-400' => $rfp->status === RfpStatus::Completed])>{{ Peso::format((float) $rfp->total_php_amount) }}</div>
                            @if ($foreign)
                                <div class="text-[0.6875rem] tabular-nums text-gray-500 dark:text-gray-400">{{ $foreign }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">
                            @if ($dueState === 'overdue')
                                <span class="flex items-center gap-1 font-bold text-danger-600 dark:text-danger-400">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-3.5" />
                                    {{ $rfp->due_date->format('M j') }} · Overdue {{ (int) $rfp->due_date->diffInDays(today()) }}d
                                </span>
                            @elseif ($dueState === 'today')
                                <span class="font-semibold text-warning-600 dark:text-warning-400">Due today</span>
                            @else
                                <span class="text-gray-500 dark:text-gray-400">{{ $rfp->due_date?->format('M j, Y') ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-gray-500 dark:text-gray-400">
                            {{ $rfp->updated_at?->diffForHumans(short: true) }}@if ($rfp->updater) · {{ str($rfp->updater->name)->limit(16) }}@endif
                        </td>
                        <td class="px-2 py-3 text-center" x-on:click.stop>
                            @if ($rfp->unread_comments_count)
                                <button
                                    type="button"
                                    title="Open messages"
                                    x-on:click="$dispatch('open-comments-thread', { type: @js($rfp->getMorphClass()), id: {{ $rfp->id }} })"
                                    class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary-100 px-1.5 py-0.5 text-[0.6875rem] font-bold text-primary-700 hover:bg-primary-200 dark:bg-primary-500/20 dark:text-primary-300"
                                >{{ $rfp->unread_comments_count }}</button>
                            @else
                                <span class="text-gray-300 dark:text-gray-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center" x-on:click.stop>
                            <div class="flex items-center justify-center gap-1 opacity-70 transition group-hover:opacity-100">
                                <a href="{{ RfpResource::getUrl('view', ['record' => $rfp]) }}" title="Open" class="rounded p-1 text-gray-500 hover:text-primary-600"><x-filament::icon icon="heroicon-m-eye" class="size-4" /></a>
                                @if (RfpResource::canEdit($rfp))
                                    <a href="{{ RfpResource::getUrl('edit', ['record' => $rfp]) }}" title="Edit" class="rounded p-1 text-gray-500 hover:text-primary-600"><x-filament::icon icon="heroicon-m-pencil-square" class="size-4" /></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-6 py-16 text-center">
                            <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="mx-auto size-10 text-gray-300" />
                            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">No requests here</p>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($hasFilters)
                                    Try another view or <button type="button" wire:click="clearFilters" class="font-semibold text-primary-600 hover:underline">clear the filters</button>.
                                @else
                                    Start one with New request.
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
        <div class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 bg-gray-50/40 px-6 py-4 text-xs text-gray-500 sm:flex-row dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400">
            <div class="flex items-center gap-3">
                <span class="tabular-nums">Showing <strong class="text-gray-900 dark:text-white">{{ $rfps->firstItem() }}–{{ $rfps->lastItem() }}</strong> of <strong class="text-gray-900 dark:text-white">{{ number_format($rfps->total()) }}</strong> requests</span>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <label class="flex items-center gap-1.5">
                    Per page
                    <select wire:model.live="perPage" class="rounded-lg border-0 bg-white py-1 ps-2.5 pe-7 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-white/10">
                        @foreach ([10, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }} rows</option>
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
                <nav class="flex items-center gap-1.5" aria-label="Pagination">
                    <button type="button" wire:click="previousPage" @disabled($rfps->onFirstPage()) class="flex size-8 items-center justify-center rounded-lg hover:bg-gray-100 disabled:opacity-40 dark:hover:bg-white/5" aria-label="Previous page">
                        <x-filament::icon icon="heroicon-m-chevron-left" class="size-4" />
                    </button>
                    @foreach ($pages as $page)
                        @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                            <span class="px-1">…</span>
                        @endif
                        <button
                            type="button"
                            wire:click="gotoPage({{ $page }})"
                            @class([
                                'flex size-8 items-center justify-center rounded-lg text-xs tabular-nums',
                                'bg-primary-600 font-bold text-white shadow-sm' => $page === $current,
                                'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $page !== $current,
                            ])
                        >{{ $page }}</button>
                    @endforeach
                    <button type="button" wire:click="nextPage" @disabled(! $rfps->hasMorePages()) class="flex size-8 items-center justify-center rounded-lg hover:bg-gray-100 disabled:opacity-40 dark:hover:bg-white/5" aria-label="Next page">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="size-4" />
                    </button>
                </nav>
            @endif
        </div>
    @endif
</section>
