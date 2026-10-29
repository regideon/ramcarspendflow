@php
    use App\Enums\RfpRequestType;
    use App\Enums\RfpStatus;
    use App\Filament\My\Pages\CreateRfp;
    use App\Filament\My\Pages\Dashboard;
    use App\Filament\My\Pages\RfpDetail;
    use Filament\Support\Icons\Heroicon;

    $summary = $this->summary();
    $counts = $this->tabCounts();
    $groups = $this->groupedRequests();

    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);

    $iconTones = [
        'sky' => 'border-sky-100 bg-sky-50 text-sky-600',
        'orange' => 'border-orange-100 bg-orange-50 text-orange-600',
        'violet' => 'border-violet-100 bg-violet-50 text-violet-600',
        'slate' => 'border-slate-200 bg-slate-100 text-slate-700',
        'amber' => 'border-amber-100 bg-amber-50 text-amber-600',
        'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-600',
        'rose' => 'border-rose-100 bg-rose-50 text-rose-600',
    ];

    $countTones = [
        'in_progress' => 'text-indigo-600',
        'for_revision' => 'text-orange-600',
        'completed' => 'text-emerald-600',
    ];
@endphp

<div x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false" class="flex flex-col gap-4 lg:gap-6">
    {{-- Mobile header --}}
    <header class="flex items-center justify-between lg:hidden">
        <a href="{{ Dashboard::getUrl() }}" aria-label="Back to home" class="my-glass flex size-10 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
            <x-filament::icon :icon="Heroicon::ChevronLeft" class="size-5" />
        </a>
        <h1 class="font-display text-lg font-bold tracking-tight text-slate-900">My requests</h1>
        <button type="button" x-on:click="filtersOpen = true" aria-label="Filters" class="my-glass relative flex size-10 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
            <x-filament::icon :icon="Heroicon::OutlinedFunnel" class="size-5" />
        </button>
    </header>

    {{-- Laptop header --}}
    <header class="hidden items-end justify-between gap-4 lg:flex">
        <div>
            <h1 class="font-display text-2xl font-extrabold tracking-tight text-slate-900">My requests</h1>
            <p class="mt-1 text-xs text-slate-500">{{ $summary['total'] }} requests · {{ $peso($summary['yearAmount']) }} total this year</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="relative w-80">
                <span class="sr-only">Search</span>
                <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search RFP #, payee or purpose" class="w-full rounded-xl border border-white/90 bg-white/60 py-2.5 pr-4 pl-10 text-xs text-slate-800 shadow-sm backdrop-blur-lg placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none" />
            </label>
            <a href="{{ CreateRfp::getUrl() }}" class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white shadow-md transition hover:bg-slate-800">
                <x-filament::icon :icon="Heroicon::Plus" class="size-4" />
                New RFP
            </a>
        </div>
    </header>

    {{-- Mobile search --}}
    <label class="relative lg:hidden">
        <span class="sr-only">Search</span>
        <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search RFP #, payee or purpose" class="w-full rounded-2xl border border-white/90 bg-white/60 py-3 pr-4 pl-10 text-sm text-slate-800 shadow-sm backdrop-blur-lg placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none" />
    </label>

    {{-- Summary tiles --}}
    <section class="grid grid-cols-3 gap-2 lg:gap-4">
        <div class="my-glass flex flex-col justify-between rounded-2xl p-2.5 lg:flex-row lg:items-center lg:p-4">
            <div>
                <span class="text-[10px] font-medium text-slate-500 lg:text-xs">In progress</span>
                <p class="mt-1 font-display text-xs font-bold text-slate-900 tabular-nums lg:text-xl lg:font-extrabold">{{ $peso($summary['inProgressAmount']) }}</p>
                <span class="mt-1 inline-block rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">{{ $summary['inProgress'] }} {{ str('request')->plural($summary['inProgress']) }}</span>
            </div>
            <span class="hidden size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 lg:flex">
                <x-filament::icon :icon="Heroicon::OutlinedClock" class="size-5" />
            </span>
        </div>

        <button type="button" wire:click="setTab('for_revision')" class="my-glass flex flex-col justify-between rounded-2xl border-l-2 border-l-orange-400 p-2.5 text-left lg:flex-row lg:items-center lg:border-l-4 lg:p-4">
            <div>
                <span class="text-[10px] font-medium text-slate-500 lg:text-xs">Needs your fix</span>
                <p class="mt-1 font-display text-xs font-bold text-orange-800 lg:text-xl lg:font-extrabold">{{ $summary['toFix'] }} {{ str('request')->plural($summary['toFix']) }}</p>
                <span class="mt-1 inline-block rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700">Action needed</span>
            </div>
            <span class="hidden size-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600 lg:flex">
                <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="size-5" />
            </span>
        </button>

        <div class="my-glass flex flex-col justify-between rounded-2xl border-l-2 border-l-emerald-400 p-2.5 lg:flex-row lg:items-center lg:border-l-4 lg:p-4">
            <div>
                <span class="text-[10px] font-medium text-slate-500 lg:text-xs">Paid this month</span>
                <p class="mt-1 font-display text-xs font-bold text-emerald-800 tabular-nums lg:text-xl lg:font-extrabold">{{ $peso($summary['paidAmount']) }}</p>
                <span class="mt-1 inline-block rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">{{ $summary['paid'] }} {{ str('request')->plural($summary['paid']) }}</span>
            </div>
            <span class="hidden size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 lg:flex">
                <x-filament::icon :icon="Heroicon::OutlinedCheckCircle" class="size-5" />
            </span>
        </div>
    </section>

    {{-- Status tabs (scrolling pills on phones, a segmented control on laptops) + filters --}}
    <div class="flex items-center justify-between gap-3">
        <nav class="-mx-4 flex items-center gap-2 overflow-x-auto px-4 py-1 lg:my-glass lg:mx-0 lg:gap-1 lg:rounded-xl lg:p-1">
            @foreach ($this->tabs() as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition lg:rounded-lg',
                        'bg-slate-900 font-semibold text-white shadow-sm' => $tab === $key,
                        'border border-white/90 bg-white/75 text-slate-600 backdrop-blur-lg hover:text-slate-900 lg:border-0 lg:bg-transparent' => $tab !== $key,
                    ])
                >
                    {{ $label }}
                    <span @class([
                        'font-mono text-[10px]',
                        'rounded-full bg-slate-700 px-1.5 text-slate-200' => $tab === $key,
                        ($countTones[$key] ?? 'text-slate-400').' font-semibold' => $tab !== $key,
                    ])>{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </nav>

        <button type="button" x-on:click="filtersOpen = true" class="my-glass hidden shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-white/80 lg:flex">
            <x-filament::icon :icon="Heroicon::OutlinedFunnel" class="size-4 text-slate-500" />
            Filters
        </button>
    </div>

    @if ($groups->isEmpty())
        {{-- Empty state --}}
        <section class="flex justify-center py-6">
            <div class="my-glass flex w-full max-w-xs flex-col items-center rounded-[28px] p-8 text-center">
                <div class="relative mb-5 flex size-28 items-center justify-center">
                    <div class="absolute inset-0 rounded-3xl bg-gradient-to-tr from-indigo-200/60 to-violet-100/70 blur-md"></div>
                    <div class="relative flex h-24 w-20 -rotate-3 flex-col rounded-2xl border border-white bg-white/90 p-2.5 shadow-xl">
                        <span class="flex size-6 items-center justify-center self-end rounded-lg bg-indigo-500 text-white shadow-sm">
                            <x-filament::icon :icon="Heroicon::Check" class="size-3.5" />
                        </span>
                        <span class="mt-3 h-1.5 w-10 rounded-full bg-slate-200"></span>
                        <span class="mt-2 h-1.5 w-14 rounded-full bg-indigo-100"></span>
                        <span class="mt-2 h-1.5 w-8 rounded-full bg-slate-200"></span>
                        <span class="absolute -bottom-2 -left-2 flex size-14 items-center justify-center rounded-xl border border-white/80 bg-gradient-to-br from-indigo-500/20 to-violet-400/20 shadow-lg backdrop-blur-md">
                            <x-filament::icon :icon="Heroicon::OutlinedDocumentText" class="size-6 text-indigo-600" />
                        </span>
                    </div>
                </div>

                <h2 class="font-display text-lg font-bold text-slate-900">Nothing here</h2>
                <p class="mt-1 max-w-[220px] text-xs text-slate-500">
                    @if (filled($search))
                        No requests match “{{ $search }}”.
                    @elseif ($tab === 'rejected')
                        No rejected requests. Nice work.
                    @else
                        No {{ str($this->tabs()[$tab])->lower() }} requests yet.
                    @endif
                </p>

                <a href="{{ CreateRfp::getUrl() }}" class="mt-6 rounded-full border border-slate-300 bg-white/70 px-5 py-2.5 text-xs font-semibold text-slate-800 shadow-sm transition hover:border-slate-800 active:scale-95">
                    Create new RFP
                </a>
            </div>
        </section>
    @else
        {{-- Phones and tablets: month-grouped cards --}}
        <section class="flex flex-col gap-5 lg:hidden">
            @foreach ($groups as $month => $requests)
                <div class="flex flex-col gap-3">
                    <div class="sticky top-0 z-10 -mx-1 flex items-center justify-between rounded-lg bg-[#f5f6fb]/80 px-2 py-1.5 backdrop-blur-md">
                        <span class="font-mono text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ $month }}</span>
                        <span class="text-[11px] font-medium text-slate-400">{{ $requests->count() }} {{ str('request')->plural($requests->count()) }} · {{ $peso($requests->sum('amount')) }}</span>
                    </div>

                    @foreach ($requests as $request)
                        @php($status = $request['status'])

                        <article @class([
                            'my-glass relative rounded-[20px] p-4 transition hover:bg-white/80',
                            'border-l-4 border-l-orange-400' => $status === RfpStatus::ForRevision,
                            'border-l-4 border-l-rose-400' => $status === RfpStatus::Rejected,
                        ])>
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-xl border {{ $iconTones[$request['tone']] }}">
                                        <x-filament::icon :icon="$request['icon']" class="size-4" />
                                    </span>
                                    <h2 class="truncate font-display text-sm font-bold text-slate-900">
                                        {{-- Stretched link: the whole card opens the RFP; inner buttons sit above it --}}
                                        <a href="{{ RfpDetail::getUrl(['number' => $request['number']]) }}" class="after:absolute after:inset-0">{{ $request['purpose'] }}</a>
                                    </h2>
                                </div>
                                <span class="shrink-0 font-display text-sm font-bold text-slate-900 tabular-nums">{{ $peso($request['amount']) }}</span>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between gap-2 text-xs">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    @if ($request['type'] === RfpRequestType::Reimbursement)
                                        <span class="shrink-0 rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-indigo-700 uppercase">Reimbursement</span>
                                    @elseif (filled($request['payee']))
                                        <span class="truncate font-medium text-slate-700">{{ $request['payee'] }}</span>
                                    @endif
                                    <span class="text-slate-300">·</span>
                                    <span class="shrink-0 font-mono text-[10px] text-slate-400">{{ $request['number'] }}</span>
                                </div>
                                <x-my.status-pill :status="$status" />
                            </div>

                            @switch($status)
                                @case(RfpStatus::ForRevision)
                                    <div class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-orange-200/60 bg-orange-50/80 p-2.5">
                                        <p class="flex items-start gap-1.5 text-[11px] leading-snug text-orange-900">
                                            <x-filament::icon :icon="Heroicon::ExclamationTriangle" class="mt-0.5 size-3.5 shrink-0 text-orange-600" />
                                            <span>{{ $request['note'] }}</span>
                                        </p>
                                        <a href="#" class="relative z-10 shrink-0 rounded-lg bg-slate-900 px-2.5 py-1.5 text-[10px] font-semibold text-white shadow-sm active:scale-95">Fix now</a>
                                    </div>
                                    @break

                                @case(RfpStatus::Draft)
                                    <div class="mt-3 flex items-center justify-between border-t border-slate-200/50 pt-3">
                                        <span class="text-[11px] font-medium text-slate-400">{{ $request['note'] }}</span>
                                        <a href="#" class="relative z-10 flex items-center gap-0.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                            Continue
                                            <x-filament::icon :icon="Heroicon::ChevronRight" class="size-3.5" />
                                        </a>
                                    </div>
                                    @break

                                @case(RfpStatus::Rejected)
                                @case(RfpStatus::Cancelled)
                                    <p class="mt-3 flex items-start gap-1.5 border-t border-rose-100 pt-2.5 text-[11px] leading-tight text-rose-700">
                                        <x-filament::icon :icon="Heroicon::OutlinedXCircle" class="size-3.5 shrink-0 text-rose-500" />
                                        {{ $request['note'] }}
                                    </p>
                                    @break

                                @case(RfpStatus::Completed)
                                    <div class="mt-3 border-t border-slate-200/50 pt-3">
                                        <x-my.stage-tracker :status="$status" :show-stage="false" />
                                        <div class="mt-1.5 flex items-center justify-between text-[10px]">
                                            <span class="flex items-center gap-1 font-semibold text-emerald-700">
                                                <x-filament::icon :icon="Heroicon::Check" class="size-3" />
                                                Paid {{ $request['paidAt']->format('M j') }}
                                            </span>
                                            <span class="font-mono text-slate-400">{{ $request['note'] }}</span>
                                        </div>
                                    </div>
                                    @break

                                @default
                                    <x-my.stage-tracker :status="$status" :note="$request['note']" class="mt-3 border-t border-slate-200/50 pt-3" />
                            @endswitch
                        </article>
                    @endforeach
                </div>
            @endforeach
        </section>

        {{-- Laptops: one glass card with a month-grouped table --}}
        <section class="my-glass hidden overflow-hidden rounded-3xl lg:block">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200/60 bg-white/30 font-mono text-[10px] tracking-wider text-slate-400 uppercase">
                        <th class="px-5 py-3 font-semibold">RFP #</th>
                        <th class="px-4 py-3 font-semibold">Purpose &amp; payee</th>
                        <th class="px-3 py-3 font-semibold">Submitted</th>
                        <th class="w-52 px-4 py-3 font-semibold">Stage</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Amount</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @foreach ($groups as $month => $requests)
                        <tr class="bg-slate-100/40">
                            <td colspan="7" class="px-5 py-2 font-mono text-[11px] font-bold tracking-wide text-slate-600 uppercase">
                                {{ $month }}
                                <span class="ml-2 font-normal text-slate-400">· {{ $requests->count() }} {{ str('request')->plural($requests->count()) }} · {{ $peso($requests->sum('amount')) }}</span>
                            </td>
                        </tr>

                        @foreach ($requests as $request)
                            @php($status = $request['status'])

                            <tr @class([
                                'transition-colors hover:bg-white/60',
                                'bg-orange-50/30 shadow-[inset_4px_0_0_0_var(--color-orange-400)]' => $status === RfpStatus::ForRevision,
                                'bg-rose-50/30 shadow-[inset_4px_0_0_0_var(--color-rose-400)]' => $status === RfpStatus::Rejected,
                            ])>
                                <td class="px-5 py-3.5 font-mono font-medium whitespace-nowrap text-slate-500">{{ $request['number'] }}</td>
                                <td class="max-w-72 px-4 py-3.5">
                                    <span class="flex items-center gap-1.5">
                                        <a href="{{ RfpDetail::getUrl(['number' => $request['number']]) }}" class="truncate font-display font-bold text-slate-900 hover:text-indigo-700">{{ $request['purpose'] }}</a>
                                        @if ($request['type'] === RfpRequestType::Reimbursement)
                                            <span class="shrink-0 rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-indigo-700 uppercase">Reimbursement</span>
                                        @endif
                                    </span>
                                    <span class="block truncate text-[11px] text-slate-400">{{ $request['payee'] ?? 'Reimbursement to you' }}</span>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">
                                    {{ $status === RfpStatus::Draft ? 'Not submitted' : $request['date']->format('M j, Y') }}
                                </td>
                                <td class="px-4 py-3.5">
                                    @switch($status)
                                        @case(RfpStatus::ForRevision)
                                            <span class="flex items-center gap-2">
                                                <span class="truncate text-[11px] font-medium text-orange-800">{{ str($request['note'])->limit(32) }}</span>
                                                <a href="#" class="shrink-0 rounded-md bg-slate-900 px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm hover:bg-slate-800">Fix now</a>
                                            </span>
                                            @break

                                        @case(RfpStatus::Draft)
                                            <span class="flex items-center gap-2 whitespace-nowrap">
                                                <span class="text-[11px] text-slate-400">{{ $request['note'] }}</span>
                                                <a href="#" class="flex items-center gap-0.5 font-semibold text-indigo-600 hover:text-indigo-800">
                                                    Continue
                                                    <x-filament::icon :icon="Heroicon::ChevronRight" class="size-3" />
                                                </a>
                                            </span>
                                            @break

                                        @case(RfpStatus::Rejected)
                                        @case(RfpStatus::Cancelled)
                                            <span class="line-clamp-2 text-[11px] font-medium text-rose-700">{{ $request['note'] }}</span>
                                            @break

                                        @case(RfpStatus::Completed)
                                            <x-my.stage-tracker :status="$status" :show-stage="false" />
                                            <span class="mt-1 block text-[10px] font-medium text-emerald-700">Paid {{ $request['paidAt']->format('M j') }} · {{ $request['note'] }}</span>
                                            @break

                                        @default
                                            <x-my.stage-tracker :status="$status" :note="$request['note']" :show-stage="false" />
                                    @endswitch
                                </td>
                                <td class="px-3 py-3.5"><x-my.status-pill :status="$status" /></td>
                                <td class="px-5 py-3.5 text-right font-display text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums">{{ $peso($request['amount']) }}</td>
                                <td class="pr-4">
                                    <a href="{{ RfpDetail::getUrl(['number' => $request['number']]) }}" aria-label="Open {{ $request['number'] }}" class="text-slate-400 hover:text-indigo-600">
                                        <x-filament::icon :icon="Heroicon::ChevronRight" class="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>

            <div class="border-t border-slate-200/60 bg-white/40 px-5 py-3.5 text-xs font-medium text-slate-500">
                Showing <span class="font-semibold text-slate-800">{{ $groups->flatten(1)->count() }}</span> of <span class="font-semibold text-slate-800">{{ $summary['total'] }}</span> requests
            </div>
        </section>
    @endif

    {{-- Filters: a bottom sheet on phones, a right-side drawer on laptops. UI only for now. --}}
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
            x-data="{ type: [], company: [], date: null, due: [], toggle(list, value) { list.includes(value) ? list.splice(list.indexOf(value), 1) : list.push(value) } }"
            class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[92vh] max-w-xl flex-col overflow-y-auto rounded-t-[32px] border-t border-white bg-white/90 p-6 pt-3 shadow-[0_-10px_40px_-10px_rgba(15,23,42,0.15)] backdrop-blur-2xl lg:inset-y-0 lg:right-0 lg:left-auto lg:max-h-none lg:w-[400px] lg:rounded-none lg:rounded-l-[28px] lg:border-t-0 lg:border-l lg:pt-6"
        >
            <span class="mx-auto my-1 h-1.5 w-12 shrink-0 rounded-full bg-slate-300 lg:hidden"></span>

            <div class="mt-2 mb-5 flex items-center justify-between lg:mt-0">
                <div>
                    <h2 class="font-display text-lg font-bold text-slate-900">Filter requests</h2>
                    <p class="text-xs text-slate-500">Narrow down by type, company, date or amount</p>
                </div>
                <button type="button" x-on:click="filtersOpen = false" aria-label="Close filters" class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                    <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                </button>
            </div>

            <div class="flex flex-col gap-5 text-xs">
                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Request type</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (RfpRequestType::cases() as $type)
                            <button type="button" x-on:click="toggle(type, '{{ $type->value }}')" :class="type.includes('{{ $type->value }}') ? 'bg-slate-900 text-white border-slate-900' : 'bg-white/80 text-slate-700 border-slate-200'" class="rounded-full border px-3.5 py-1.5 font-medium">{{ $type->getLabel() }}</button>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Company</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['Ramcar Inc.', 'Ramcar Technology', 'Philippine Batteries'] as $company)
                            <button type="button" x-on:click="toggle(company, @js($company))" :class="company.includes(@js($company)) ? 'bg-slate-900 text-white border-slate-900' : 'bg-white/80 text-slate-700 border-slate-200'" class="rounded-full border px-3 py-1.5 font-medium">{{ $company }}</button>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Date submitted</legend>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['This month', 'Last 3 months', 'Custom range'] as $range)
                            <button type="button" x-on:click="date = date === @js($range) ? null : @js($range)" :class="date === @js($range) ? 'bg-slate-900 text-white border-slate-900' : 'bg-white/80 text-slate-700 border-slate-200'" class="rounded-xl border py-2 text-center font-medium">{{ $range }}</button>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Amount range (PHP)</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['Min', 'Max'] as $bound)
                            <label class="relative">
                                <span class="sr-only">{{ $bound }}</span>
                                <span class="absolute top-1/2 left-3 -translate-y-1/2 font-mono text-slate-400">₱</span>
                                <input type="text" inputmode="decimal" placeholder="{{ $bound }}" class="w-full rounded-xl border border-slate-200/70 bg-white/70 py-2 pr-3 pl-7 font-mono text-xs text-slate-800 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none" />
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 font-semibold text-slate-700">Due</legend>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-on:click="toggle(due, 'overdue')" :class="due.includes('overdue') ? 'ring-2 ring-rose-400' : ''" class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 font-medium text-rose-700">Overdue</button>
                        <button type="button" x-on:click="toggle(due, 'week')" :class="due.includes('week') ? 'ring-2 ring-amber-400' : ''" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 font-medium text-amber-800">Due this week</button>
                    </div>
                </fieldset>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3 border-t lg:mt-auto border-slate-200/70 pt-4 lg:pt-5">
                <button type="button" x-on:click="type = []; company = []; date = null; due = []" class="px-2 py-3 text-xs font-semibold text-slate-500 hover:text-slate-800">Reset</button>
                <button type="button" x-on:click="filtersOpen = false" class="flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-slate-900 px-4 py-3.5 font-display text-xs font-bold text-white shadow-lg transition active:scale-95">
                    Show {{ $summary['total'] }} results
                    <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                </button>
            </div>
        </div>
    </div>
</div>
