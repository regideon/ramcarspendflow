@php
    use App\Enums\RfpRequestType;
    use App\Filament\My\Pages\Approvals;
    use App\Filament\My\Pages\RfpDetail;
    use Filament\Support\Icons\Heroicon;

    $summary = $this->summary();
    $waiting = $tab === 'waiting' ? $this->waiting() : collect();
    $decided = $tab === 'done' ? $this->decided() : collect();

    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $detailUrl = fn (array $rfp, array $query = []): string => RfpDetail::getUrl(['number' => $rfp['number'], ...$query]);
    $initials = fn (string $name): string => str($name)->explode(' ')->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->join('');

    $avatarTones = [
        'amber' => 'bg-amber-100 text-amber-800',
        'rose' => 'bg-rose-100 text-rose-700',
        'indigo' => 'bg-indigo-100 text-indigo-700',
        'sky' => 'bg-sky-100 text-sky-700',
        'orange' => 'bg-orange-100 text-orange-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
    ];

    $dueTones = [
        'amber' => 'bg-amber-100 text-amber-800 [&>span]:bg-amber-500',
        'rose' => 'bg-rose-100 text-rose-700 [&>span]:bg-rose-500',
        'slate' => 'bg-slate-100 text-slate-600 [&>span]:bg-slate-400',
    ];

    $decisions = [
        'approved' => ['Approved', 'bg-emerald-50 text-emerald-700 ring-emerald-200/70', 'text-emerald-700'],
        'returned' => ['Returned', 'bg-orange-50 text-orange-700 ring-orange-200/70', 'text-orange-700'],
        'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 ring-rose-200/70', 'text-rose-700'],
    ];

    $isEmpty = $tab === 'waiting' ? $waiting->isEmpty() : $decided->isEmpty();
    $activeFilters = $this->activeFilterCount();
@endphp

<div x-data="{ filtersOpen: false }" x-on:keydown.escape.window="filtersOpen = false" class="flex flex-col gap-4 lg:gap-6">
    {{-- Mobile header --}}
    <header class="flex items-start justify-between gap-3 lg:hidden">
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-extrabold tracking-tight text-slate-900">Approvals</h1>
            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $summary['count'] }} waiting for you · {{ $peso($summary['amount']) }} total</p>
        </div>
        <button type="button" x-on:click="filtersOpen = true" aria-label="Filters" class="my-glass relative flex size-10 shrink-0 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
            <x-filament::icon :icon="Heroicon::OutlinedFunnel" class="size-5" />
            @if ($activeFilters)
                <span class="absolute -top-1 -right-1 flex size-4.5 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white ring-2 ring-[#f5f6fb]">{{ $activeFilters }}</span>
            @endif
        </button>
    </header>

    {{-- Mobile search --}}
    <label class="relative lg:hidden">
        <span class="sr-only">Search</span>
        <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search payee, purpose, requestor or RFP #" class="w-full rounded-2xl border border-white/90 bg-white/60 py-3 pr-4 pl-10 text-sm text-slate-800 shadow-sm backdrop-blur-lg placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none" />
    </label>

    {{-- Laptop header --}}
    <header class="hidden items-end justify-between gap-4 lg:flex">
        <div>
            <h1 class="font-display text-2xl font-extrabold tracking-tight text-slate-900">Approvals</h1>
            <p class="mt-1 text-xs text-slate-500">RFPs that need your sign-off, and the ones you already decided</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="relative w-72">
                <span class="sr-only">Search</span>
                <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search payee, purpose or RFP #" class="w-full rounded-xl border border-white/90 bg-white/60 py-2.5 pr-4 pl-10 text-xs text-slate-800 shadow-sm backdrop-blur-lg placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none" />
            </label>

            <button type="button" x-on:click="filtersOpen = true" class="my-glass relative flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-white/80">
                <x-filament::icon :icon="Heroicon::OutlinedFunnel" class="size-4 text-slate-500" />
                Filters
            @if ($activeFilters)
                <span class="absolute -top-1 -right-1 flex size-4.5 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white ring-2 ring-[#f5f6fb]">{{ $activeFilters }}</span>
            @endif
            </button>

            @if ($tab === 'waiting')
                <label class="relative">
                    <span class="sr-only">Sort</span>
                    <select wire:model.live="sort" class="my-glass appearance-none rounded-xl border-0 py-2.5 pr-9 pl-3.5 text-xs font-semibold text-slate-700 focus:ring-3 focus:ring-indigo-500/15 focus:outline-none">
                        @foreach ($this->sorts() as $key => $label)
                            <option value="{{ $key }}">Sort: {{ $label }}</option>
                        @endforeach
                    </select>
                    <x-filament::icon :icon="Heroicon::ChevronDown" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400" />
                </label>
            @endif
        </div>
    </header>

    {{-- Laptop summary tiles --}}
    <section class="hidden grid-cols-3 gap-4 lg:grid">
        <button type="button" wire:click="setTab('waiting')" class="my-glass flex items-center justify-between rounded-2xl p-4 text-left transition hover:bg-white/80">
            <div>
                <span class="text-xs font-medium text-slate-500">Waiting for you</span>
                <p class="mt-1 font-display text-xl font-extrabold text-slate-900 tabular-nums">{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'RFP' : 'RFPs' }}</p>
            </div>
            <span class="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <x-filament::icon :icon="Heroicon::OutlinedClock" class="size-5" />
            </span>
        </button>

        <div @class(['my-glass flex items-center justify-between rounded-2xl p-4', 'border-l-4 border-l-rose-400' => $summary['urgent'] > 0])>
            <div>
                <span class="text-xs font-medium text-slate-500">Due today or overdue</span>
                <p @class(['mt-1 font-display text-xl font-extrabold tabular-nums', 'text-rose-700' => $summary['urgent'] > 0, 'text-slate-900' => $summary['urgent'] === 0])>{{ $summary['urgent'] }} {{ $summary['urgent'] === 1 ? 'RFP' : 'RFPs' }}</p>
            </div>
            <span class="flex size-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="size-5" />
            </span>
        </div>

        <div class="my-glass flex items-center justify-between rounded-2xl p-4">
            <div>
                <span class="text-xs font-medium text-slate-500">Total amount waiting</span>
                <p class="mt-1 font-display text-xl font-extrabold text-slate-900 tabular-nums">{{ $peso($summary['amount']) }}</p>
            </div>
            <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <x-filament::icon :icon="Heroicon::OutlinedBanknotes" class="size-5" />
            </span>
        </div>
    </section>

    {{-- Tabs: a full-width segmented control on phones --}}
    <nav class="flex items-center gap-1 rounded-full border border-white/80 bg-indigo-100/50 p-1 backdrop-blur-xl lg:self-start lg:rounded-xl lg:bg-white/55">
        @foreach ($this->tabs() as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class([
                    'flex flex-1 items-center justify-center gap-2 rounded-full px-4 py-2 text-sm transition lg:flex-none lg:rounded-lg lg:py-1.5 lg:text-xs',
                    'bg-slate-900 font-semibold text-white shadow-sm' => $tab === $key,
                    'font-medium text-slate-600 hover:text-slate-900' => $tab !== $key,
                ])
            >
                {{ $label }}
                @if ($key === 'waiting')
                    <span @class([
                        'flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-bold',
                        'bg-indigo-500 text-white' => $tab === $key,
                        'bg-white/80 text-slate-600' => $tab !== $key,
                    ])>{{ $summary['count'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    {{-- Mobile sort chips --}}
    @if ($tab === 'waiting' && $waiting->count() > 1)
        <div class="-mx-4 flex items-center gap-2 overflow-x-auto px-4 lg:hidden">
            @foreach ($this->sorts() as $key => $label)
                <button
                    type="button"
                    wire:click="setSort('{{ $key }}')"
                    @class([
                        'flex shrink-0 items-center gap-1 rounded-full px-3.5 py-1.5 text-xs transition',
                        'bg-slate-900 font-semibold text-white shadow-sm' => $sort === $key,
                        'border border-white/90 bg-white/70 font-medium text-slate-600 backdrop-blur-lg' => $sort !== $key,
                    ])
                >
                    @if ($sort === $key)
                        <x-filament::icon :icon="Heroicon::Check" class="size-3.5" />
                    @endif
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Reviewed by Me: filter by decision --}}
    @if ($tab === 'done')
        <div class="-mx-4 flex items-center gap-2 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            @foreach ($this->decisionFilters() as $key => $filter)
                <button
                    type="button"
                    wire:click="setDecision('{{ $key }}')"
                    @class([
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs transition',
                        'bg-slate-900 font-semibold text-white shadow-sm' => $decision === $key,
                        'border border-white/90 bg-white/70 font-medium text-slate-600 backdrop-blur-lg hover:text-slate-900' => $decision !== $key,
                    ])
                >
                    @if ($key !== 'all')
                        <span @class(['size-1.5 rounded-full', 'bg-emerald-500' => $key === 'approved', 'bg-orange-500' => $key === 'returned', 'bg-rose-500' => $key === 'rejected'])></span>
                    @endif
                    {{ $filter['label'] }}
                    <span @class(['tabular-nums', 'text-white/70' => $decision === $key, 'text-slate-400' => $decision !== $key])>{{ $filter['count'] }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if ($isEmpty)
        {{-- Empty state --}}
        <section class="flex justify-center py-4">
            <div class="my-glass relative flex w-full max-w-sm flex-col items-center overflow-hidden rounded-[28px] px-6 py-10 text-center">
                <span class="pointer-events-none absolute -top-16 left-1/2 size-48 -translate-x-1/2 rounded-full bg-indigo-200/40 blur-2xl"></span>

                <div class="relative mb-6 flex size-28 items-center justify-center">
                    <span class="absolute inset-0 animate-pulse rounded-full bg-emerald-200/40"></span>
                    <span class="absolute inset-2.5 rounded-full bg-white/80 shadow-[0_4px_20px_rgba(5,150,105,0.1)] backdrop-blur-md"></span>
                    <span class="relative flex size-14 items-center justify-center rounded-full bg-gradient-to-tr from-emerald-600 via-indigo-600 to-violet-500 text-white shadow-[0_8px_16px_rgba(79,70,229,0.25)]">
                        <x-filament::icon :icon="filled($search) || $activeFilters ? Heroicon::MagnifyingGlass : Heroicon::Check" class="size-7" />
                    </span>
                </div>

                <h2 class="relative font-display text-xl font-bold tracking-tight text-slate-900">
                    @if (filled($search) || $activeFilters)
                        No matches
                    @elseif ($tab === 'done' && $decision !== 'all')
                        Nothing {{ str($decision)->lower() }} yet
                    @elseif ($tab === 'waiting')
                        You're all caught up
                    @else
                        No decisions yet
                    @endif
                </h2>
                <p class="relative mt-2 max-w-[260px] text-sm leading-relaxed text-slate-500">
                    @if (filled($search))
                        Nothing here matches “{{ $search }}”.
                    @elseif ($activeFilters)
                        Nothing here matches your filters.
                    @elseif ($tab === 'done' && $decision !== 'all')
                        RFPs you mark {{ str($decision)->lower() }} will show up here.
                    @elseif ($tab === 'waiting')
                        New requests that need your sign-off will show up here.
                    @else
                        RFPs you approve, return or reject will show up here.
                    @endif
                </p>

                @if ($activeFilters && blank($search))
                    <button type="button" wire:click="resetFilters" class="relative mt-7 rounded-full bg-white/80 px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-white active:scale-95">Clear filters</button>
                @elseif (filled($search))
                    <button type="button" wire:click="$set('search', '')" class="relative mt-7 rounded-full bg-white/80 px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-white active:scale-95">Clear search</button>
                @elseif ($tab === 'done' && $decision !== 'all')
                    <button type="button" wire:click="setDecision('all')" class="relative mt-7 rounded-full bg-white/80 px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-white active:scale-95">Show all</button>
                @elseif ($tab === 'waiting')
                    <button type="button" wire:click="setTab('done')" class="relative mt-7 flex items-center gap-2 rounded-full bg-white/80 px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-white active:scale-95">
                        View past decisions
                        <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4 text-slate-500" />
                    </button>
                @endif
            </div>
        </section>
    @elseif ($tab === 'waiting')
        {{-- Phones and tablets: one card per RFP --}}
        <section class="flex flex-col gap-3 lg:hidden">
            @foreach ($waiting as $rfp)
                <article class="my-glass relative overflow-hidden rounded-[20px] p-4">
                    <span @class([
                        'absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent to-transparent',
                        'via-amber-400/50' => $rfp['dueTone'] === 'amber',
                        'via-rose-500/50' => $rfp['dueTone'] === 'rose',
                        'via-indigo-400/30' => $rfp['dueTone'] === 'slate',
                    ])></span>

                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full font-display text-sm font-bold {{ $avatarTones[$rfp['tone']] }}">{{ $initials($rfp['payee']) }}</span>
                            <div class="min-w-0">
                                <a href="{{ $detailUrl($rfp) }}" class="block truncate font-display text-base font-bold text-slate-900">{{ $rfp['payee'] }}</a>
                                <p class="truncate text-xs text-slate-500">{{ $rfp['purpose'] }}</p>
                            </div>
                        </div>
                        <span class="flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $dueTones[$rfp['dueTone']] }}">
                            <span class="size-1.5 rounded-full"></span>
                            {{ $rfp['due'] }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-3">
                        <span class="font-display text-[22px] leading-tight font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $peso($rfp['amount']) }}</span>
                        <span class="flex items-center gap-1.5">
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-medium text-slate-500">{{ $rfp['number'] }}</span>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                'bg-indigo-50 text-indigo-700' => $rfp['type'] === RfpRequestType::Reimbursement,
                                'bg-slate-100 text-slate-600' => $rfp['type'] !== RfpRequestType::Reimbursement,
                            ])>{{ $rfp['type']->getLabel() }}</span>
                        </span>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                        <span class="flex min-w-0 items-center gap-1.5 text-slate-600">
                            <x-filament::icon :icon="Heroicon::OutlinedUser" class="size-4 shrink-0 text-slate-400" />
                            <span class="truncate">{{ $rfp['requestor'] }} · {{ $rfp['department'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold text-slate-900">Your step: {{ $rfp['myStep'] }} of {{ $rfp['approvers'] }}</span>
                    </div>
                    <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat({{ $rfp['approvers'] }}, minmax(0, 1fr))">
                        @foreach (range(1, $rfp['approvers']) as $step)
                            <span @class(['h-1.5 rounded-full', 'bg-indigo-600' => $step <= $rfp['myStep'], 'bg-indigo-100' => $step > $rfp['myStep']])></span>
                        @endforeach
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        <a href="{{ $detailUrl($rfp) }}" class="flex items-center justify-center rounded-xl border border-indigo-100 bg-white/80 py-2.5 text-sm font-semibold text-indigo-600 transition active:scale-[0.98]">Review</a>
                        <a href="{{ $detailUrl($rfp, ['decide' => 'approve']) }}" class="flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/25 transition active:scale-[0.98]">
                            <x-filament::icon :icon="Heroicon::Check" class="size-4" />
                            Approve
                        </a>
                    </div>
                </article>
            @endforeach
        </section>

        {{-- Laptops: one table --}}
        <section class="my-glass hidden overflow-hidden rounded-3xl lg:block">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200/60 bg-white/30 font-mono text-[10px] tracking-wider text-slate-400 uppercase">
                        <th class="py-3 pr-3 pl-5 font-semibold">Payee &amp; purpose</th>
                        <th class="px-3 py-3 font-semibold">Requestor</th>
                        <th class="px-3 py-3 font-semibold">Due</th>
                        <th class="px-3 py-3 font-semibold">Your step</th>
                        <th class="px-3 py-3 text-right font-semibold">Amount</th>
                        <th class="py-3 pr-5 pl-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @foreach ($waiting as $rfp)
                        <tr @class([
                            'transition-colors hover:bg-white/60',
                            'bg-rose-50/30 shadow-[inset_4px_0_0_0_var(--color-rose-400)]' => $rfp['dueTone'] === 'rose',
                        ])>
                            <td class="max-w-80 py-3.5 pr-3 pl-5">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl font-display text-xs font-bold {{ $avatarTones[$rfp['tone']] }}">{{ $initials($rfp['payee']) }}</span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <a href="{{ $detailUrl($rfp) }}" class="truncate font-display text-sm font-bold text-slate-900 hover:text-indigo-700">{{ $rfp['payee'] }}</a>
                                            @if ($rfp['type'] === RfpRequestType::Reimbursement)
                                                <span class="shrink-0 rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-indigo-700 uppercase">Reimbursement</span>
                                            @endif
                                        </span>
                                        <span class="block truncate text-[11px] text-slate-500">{{ $rfp['purpose'] }}</span>
                                        <span class="block font-mono text-[10px] text-slate-400">{{ $rfp['number'] }}</span>
                                    </span>
                                </span>
                            </td>
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <span class="block font-medium text-slate-800">{{ $rfp['requestor'] }}</span>
                                <span class="block text-[11px] text-slate-400">{{ $rfp['department'] }}</span>
                            </td>
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $dueTones[$rfp['dueTone']] }}">
                                    <span class="size-1.5 rounded-full"></span>
                                    {{ $rfp['due'] }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="block w-24 text-[11px] font-medium text-slate-600">Step {{ $rfp['myStep'] }} of {{ $rfp['approvers'] }}</span>
                                <span class="mt-1.5 grid w-24 gap-1" style="grid-template-columns: repeat({{ $rfp['approvers'] }}, minmax(0, 1fr))">
                                    @foreach (range(1, $rfp['approvers']) as $step)
                                        <span @class(['h-1.5 rounded-full', 'bg-indigo-600' => $step <= $rfp['myStep'], 'bg-indigo-100' => $step > $rfp['myStep']])></span>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-3 py-3.5 text-right font-display text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums">{{ $peso($rfp['amount']) }}</td>
                            <td class="py-3.5 pr-5 pl-3 whitespace-nowrap">
                                <span class="flex items-center justify-end gap-1.5">
                                    <a href="{{ $detailUrl($rfp) }}" class="rounded-lg px-3 py-1.5 font-semibold text-slate-600 transition hover:bg-white hover:text-slate-900">Review</a>
                                    <a href="{{ $detailUrl($rfp, ['decide' => 'approve']) }}" class="rounded-lg bg-indigo-600 px-3.5 py-1.5 font-semibold text-white shadow-sm transition hover:bg-indigo-500">Approve</a>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex items-center justify-between border-t border-slate-200/60 bg-white/30 px-5 py-3 text-xs">
                <span class="text-slate-500">Showing {{ $waiting->count() }} of {{ $summary['count'] }}</span>
                <span class="flex items-center gap-3">
                    <span class="font-mono text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Total</span>
                    <span class="font-display text-base font-bold text-slate-900 tabular-nums">{{ $peso($waiting->sum('amount')) }}</span>
                </span>
            </div>
        </section>
    @else
        {{-- Phones and tablets: past decisions grouped by month --}}
        <section class="flex flex-col gap-5 lg:hidden">
            @foreach ($decided as $month => $rfps)
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between px-1">
                        <span class="font-display text-base font-bold text-slate-900">{{ $month }}</span>
                        <span class="rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium text-slate-500">{{ $rfps->count() }} {{ str('decision')->plural($rfps->count()) }}</span>
                    </div>

                    @foreach ($rfps as $rfp)
                        @php([$decisionLabel, $decisionPill, $decisionText] = $decisions[$rfp['decision']])

                        <article class="my-glass relative rounded-[20px] p-4 transition hover:bg-white/80">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="truncate font-display text-base font-bold text-slate-900">
                                        <a href="{{ $detailUrl($rfp) }}" class="after:absolute after:inset-0">{{ $rfp['payee'] }}</a>
                                    </h2>
                                    <p class="truncate text-xs text-slate-500">{{ $rfp['purpose'] }}</p>
                                </div>
                                <span class="shrink-0 font-display text-base font-bold text-slate-900 tabular-nums">{{ $peso($rfp['amount']) }}</span>
                            </div>

                            @if (filled($rfp['remarks']))
                                <p class="mt-2.5 flex items-start gap-1.5 text-[11px] leading-snug {{ $decisionText }}">
                                    <x-filament::icon :icon="Heroicon::OutlinedChatBubbleBottomCenterText" class="mt-px size-3.5 shrink-0" />
                                    {{ $rfp['remarks'] }}
                                </p>
                            @endif

                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-200/50 pt-3 text-xs">
                                <span class="flex min-w-0 items-center gap-1.5 text-slate-600">
                                    <x-filament::icon :icon="Heroicon::ArrowLongRight" class="size-4 shrink-0 text-slate-400" />
                                    <span class="truncate">{{ Approvals::whereNow($rfp) }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span class="text-[11px] text-slate-400">{{ $rfp['decidedAt']->format('M j') }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 {{ $decisionPill }}">{{ $decisionLabel }}</span>
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach
        </section>

        {{-- Laptops: month-grouped table --}}
        <section class="my-glass hidden overflow-hidden rounded-3xl lg:block">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200/60 bg-white/30 font-mono text-[10px] tracking-wider text-slate-400 uppercase">
                        <th class="py-3 pr-3 pl-5 font-semibold">Payee &amp; purpose</th>
                        <th class="px-3 py-3 font-semibold">Requestor</th>
                        <th class="px-3 py-3 font-semibold">Your decision</th>
                        <th class="px-3 py-3 font-semibold">Where it is now</th>
                        <th class="px-3 py-3 text-right font-semibold">Amount</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @foreach ($decided as $month => $rfps)
                        <tr class="bg-slate-100/40">
                            <td colspan="6" class="px-5 py-2 font-mono text-[11px] font-bold tracking-wide text-slate-600 uppercase">
                                {{ $month }}
                                <span class="ml-2 font-normal text-slate-400">· {{ $rfps->count() }} {{ str('decision')->plural($rfps->count()) }}</span>
                            </td>
                        </tr>

                        @foreach ($rfps as $rfp)
                            @php([$decisionLabel, $decisionPill, $decisionText] = $decisions[$rfp['decision']])

                            <tr class="transition-colors hover:bg-white/60">
                                <td class="max-w-80 py-3.5 pr-3 pl-5">
                                    <a href="{{ $detailUrl($rfp) }}" class="block truncate font-display text-sm font-bold text-slate-900 hover:text-indigo-700">{{ $rfp['payee'] }}</a>
                                    <span class="block truncate text-[11px] text-slate-500">{{ $rfp['purpose'] }}</span>
                                    <span class="block font-mono text-[10px] text-slate-400">{{ $rfp['number'] }}</span>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap">
                                    <span class="block font-medium text-slate-800">{{ $rfp['requestor'] }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ $rfp['department'] }}</span>
                                </td>
                                <td class="max-w-64 px-3 py-3.5">
                                    <span class="flex items-center gap-2 whitespace-nowrap">
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 {{ $decisionPill }}">{{ $decisionLabel }}</span>
                                        <span class="text-[11px] text-slate-400">{{ $rfp['decidedAt']->format('M j, Y') }}</span>
                                    </span>
                                    @if (filled($rfp['remarks']))
                                        <span class="mt-1 block truncate text-[11px] {{ $decisionText }}" title="{{ $rfp['remarks'] }}">{{ $rfp['remarks'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-slate-600">{{ Approvals::whereNow($rfp) }}</td>
                                <td class="px-3 py-3.5 text-right font-display text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums">{{ $peso($rfp['amount']) }}</td>
                                <td class="py-3.5 pr-4">
                                    <a href="{{ $detailUrl($rfp) }}" aria-label="Open {{ $rfp['number'] }}" class="flex size-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white hover:text-slate-700">
                                        <x-filament::icon :icon="Heroicon::ChevronRight" class="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    {{-- Filters for approvers: a bottom sheet on phones, a right-side drawer on laptops --}}
    <div x-cloak x-show="filtersOpen" class="fixed inset-0 z-50">
        <div x-show="filtersOpen" x-transition.opacity x-on:click="filtersOpen = false" class="absolute inset-0 bg-slate-900/25 backdrop-blur-[2px]"></div>

        <div
            x-show="filtersOpen"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-full lg:translate-x-full lg:translate-y-0"
            x-transition:enter-end="translate-x-0 translate-y-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-x-0 translate-y-0"
            x-transition:leave-end="translate-y-full lg:translate-x-full lg:translate-y-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="approval-filters-title"
            class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[92vh] max-w-xl flex-col overflow-y-auto rounded-t-[32px] border-t border-white bg-white/90 p-6 pt-3 shadow-[0_-10px_40px_-10px_rgba(15,23,42,0.15)] backdrop-blur-2xl lg:inset-y-0 lg:right-0 lg:left-auto lg:max-h-none lg:w-[400px] lg:rounded-none lg:rounded-l-[28px] lg:border-t-0 lg:border-l lg:pt-6"
        >
            <span class="mx-auto my-1 h-1.5 w-12 shrink-0 rounded-full bg-slate-300 lg:hidden"></span>

            <div class="mt-2 mb-5 flex items-center justify-between lg:mt-0">
                <div>
                    <h2 id="approval-filters-title" class="font-display text-lg font-bold text-slate-900">Filter approvals</h2>
                    <p class="text-xs text-slate-500">{{ $tab === 'done' ? 'Narrow down what you reviewed' : 'Find what to approve first' }}</p>
                </div>
                <button type="button" x-on:click="filtersOpen = false" aria-label="Close filters" class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                    <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                </button>
            </div>

            <div class="flex flex-col gap-5 text-xs">
                @if ($tab === 'waiting')
                    <fieldset>
                        <legend class="mb-2 font-semibold text-slate-700">Due</legend>
                        <div class="flex flex-wrap gap-2">
                            <label class="cursor-pointer rounded-full border border-slate-200 bg-white/80 px-3.5 py-1.5 text-center font-medium text-slate-700 transition has-checked:border-slate-900 has-checked:bg-slate-900 has-checked:text-white">
                                <input type="radio" wire:model.live="filters.due" value="" class="sr-only" />
                                Any
                            </label>
                            @foreach (Approvals::DUE_FILTERS as $key => $label)
                                <label @class([
                                    'cursor-pointer rounded-full border bg-white/80 px-3.5 py-1.5 text-center font-medium transition has-checked:ring-2',
                                    'border-rose-200 text-rose-700 has-checked:bg-rose-50 has-checked:ring-rose-400' => $key === 'overdue',
                                    'border-amber-200 text-amber-800 has-checked:bg-amber-50 has-checked:ring-amber-400' => $key === 'today',
                                    'border-slate-200 text-slate-700 has-checked:bg-slate-50 has-checked:ring-slate-400' => $key === 'week',
                                ])>
                                    <input type="radio" wire:model.live="filters.due" value="{{ $key }}" class="sr-only" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @else
                    <fieldset>
                        <legend class="mb-2 font-semibold text-slate-700">Date you decided</legend>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="cursor-pointer rounded-xl border border-slate-200 bg-white/80 py-2 text-center font-medium text-slate-700 transition has-checked:border-slate-900 has-checked:bg-slate-900 has-checked:text-white">
                                <input type="radio" wire:model.live="filters.decided" value="" class="sr-only" />
                                Any time
                            </label>
                            @foreach (Approvals::DECIDED_FILTERS as $key => $label)
                                <label class="cursor-pointer rounded-xl border border-slate-200 bg-white/80 py-2 text-center font-medium text-slate-700 transition has-checked:border-slate-900 has-checked:bg-slate-900 has-checked:text-white">
                                    <input type="radio" wire:model.live="filters.decided" value="{{ $key }}" class="sr-only" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Request type</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (RfpRequestType::cases() as $type)
                            <label class="cursor-pointer rounded-full border border-slate-200 bg-white/80 px-3.5 py-1.5 font-medium text-slate-700 transition has-checked:border-slate-900 has-checked:bg-slate-900 has-checked:text-white">
                                <input type="checkbox" wire:model.live="filters.type" value="{{ $type->value }}" class="sr-only" />
                                {{ $type->getLabel() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($departments = $this->departments())
                    <fieldset>
                        <legend class="mb-2 font-semibold text-slate-700">Requestor’s department</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($departments as $department)
                                <label class="cursor-pointer rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 font-medium text-slate-700 transition has-checked:border-slate-900 has-checked:bg-slate-900 has-checked:text-white">
                                    <input type="checkbox" wire:model.live="filters.department" value="{{ $department }}" class="sr-only" />
                                    {{ $department }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Amount range (PHP)</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['min' => 'Min', 'max' => 'Max'] as $key => $bound)
                            <label class="relative">
                                <span class="sr-only">{{ $bound }}</span>
                                <span class="absolute top-1/2 left-3 -translate-y-1/2 font-mono text-slate-400">₱</span>
                                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="filters.{{ $key }}" placeholder="{{ $bound }}" class="w-full rounded-xl border border-slate-200/70 bg-white/70 py-2 pr-3 pl-7 font-mono text-xs text-slate-800 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none" />
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3 border-t border-slate-200/70 pt-4 lg:mt-auto lg:pt-5">
                <button type="button" wire:click="resetFilters" class="px-2 py-3 text-xs font-semibold text-slate-500 hover:text-slate-800">Reset</button>
                <button type="button" x-on:click="filtersOpen = false" class="flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-slate-900 px-4 py-3.5 font-display text-xs font-bold text-white shadow-lg transition active:scale-95">
                    @php($results = $this->resultCount())
                    Show {{ $results }} {{ $results === 1 ? 'result' : 'results' }}
                    <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                </button>
            </div>
        </div>
    </div>
</div>
