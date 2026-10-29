@php
    use App\Filament\My\Pages\Approvals;
    use App\Filament\My\Pages\MyRequests;
    use App\Filament\My\Pages\Profile;
    use App\Filament\My\Pages\RfpDetail;
    use App\Filament\My\Support\SampleNotifications;
    use Filament\Support\Icons\Heroicon;

    $user = filament()->auth()->user();
    $isApprover = $this->isApprover();
    $summary = $this->approvalSummary();
    $requests = $this->requestSummary();

    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);

    $pillTones = [
        'amber' => 'border-amber-200/60 bg-amber-50 text-amber-700',
        'rose' => 'border-rose-200/60 bg-rose-50 text-rose-700',
        'slate' => 'border-slate-200/60 bg-slate-100 text-slate-600',
        'indigo' => 'border-indigo-200/60 bg-indigo-50 text-indigo-700',
        'emerald' => 'border-emerald-200/60 bg-emerald-50 text-emerald-700',
    ];

    $approvals = $this->approvals();
    $dueSoon = $approvals->filter(fn (array $rfp): bool => $rfp['dueDate']->lte(today()->addWeek()))->count();
    $detailUrl = fn (array $rfp): string => RfpDetail::getUrl(['number' => $rfp['number']]);

    $iconTones = [
        'sky' => 'bg-sky-50 text-sky-600',
        'orange' => 'bg-orange-50 text-orange-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'slate' => 'bg-slate-100 text-slate-700',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
    ];
@endphp

<div class="flex flex-col gap-6 lg:gap-8">
    {{-- Greeting: a plain header on phones, a glass card on laptops --}}
    <section class="flex items-center justify-between gap-4 lg:my-glass lg:rounded-2xl lg:p-6">
        <div class="flex min-w-0 items-center gap-3 lg:gap-4">
            <a href="{{ Profile::getUrl() }}" aria-label="Profile" class="relative shrink-0">
                <img src="{{ filament()->getUserAvatarUrl($user) }}" alt="" class="size-11 rounded-full object-cover shadow-sm ring-2 ring-white/80 lg:size-14 lg:rounded-2xl" />
                <span class="absolute right-0 bottom-0 size-3 rounded-full bg-emerald-500 ring-2 ring-white"></span>
            </a>

            <div class="flex min-w-0 flex-col">
                <p class="flex flex-wrap items-baseline gap-x-1.5">
                    <span class="text-xs font-medium text-slate-500 lg:text-base lg:text-slate-900 lg:font-display lg:font-semibold">{{ $this->greeting() }},</span>
                    <span class="truncate font-display text-base font-bold tracking-tight text-slate-900 lg:text-xl">{{ $user->name }}</span>
                </p>
                <span class="mt-1 inline-flex self-start rounded-full border border-slate-200/60 bg-slate-100/80 px-2 py-0.5 text-[10px] font-medium text-slate-600 lg:text-xs">
                    {{ $isApprover ? 'Department Head' : 'Requestor' }} · Ramcar Inc.
                </span>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <button
                type="button"
                aria-label="Notifications"
                x-data="{ unread: @js(SampleNotifications::for($user)->where('unread', true)->count()) }"
                x-on:notifications-unread.window="unread = $event.detail"
                x-on:click="$dispatch('open-notifications')"
                class="relative flex size-9 items-center justify-center rounded-full border border-white/90 bg-white/80 text-slate-700 shadow-sm backdrop-blur-md transition active:scale-95 lg:size-11 lg:rounded-xl"
            >
                <x-filament::icon :icon="Heroicon::OutlinedBell" class="size-5" />
                <span x-show="unread" class="absolute top-2 right-2 size-2 rounded-full bg-rose-500 ring-1 ring-white lg:top-2.5 lg:right-2.5"></span>
            </button>
            <button type="button" aria-label="Messages" x-on:click="$dispatch('open-comments-inbox')" class="flex size-9 items-center justify-center rounded-full border border-white/90 bg-white/80 text-slate-700 shadow-sm backdrop-blur-md transition active:scale-95 lg:size-11 lg:rounded-xl">
                <x-filament::icon :icon="Heroicon::OutlinedChatBubbleOvalLeft" class="size-5" />
            </button>
        </div>
    </section>

    <div @class([
        'grid grid-cols-1 items-start gap-6 lg:gap-8',
        'lg:grid-cols-12' => $isApprover,
    ])>
        @if ($isApprover)
            {{-- Approvals: always first for matrix approvers --}}
            <div class="flex flex-col gap-4 lg:col-span-7 lg:gap-6">
                {{-- Hero: a dark "bank card" with soft light --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-6 text-white shadow-[0_24px_48px_-16px_rgba(30,27,75,0.55)] ring-1 ring-white/10 lg:p-7">
                    <div class="pointer-events-none absolute -top-24 -right-16 size-64 rounded-full bg-indigo-500/40 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-28 -left-10 size-56 rounded-full bg-violet-500/25 blur-3xl"></div>
                    <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full border border-white/10 lg:size-60"></div>
                    <div class="pointer-events-none absolute -top-4 -right-4 size-28 rounded-full border border-white/5 lg:size-36"></div>
                    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,transparent_40%,rgba(255,255,255,0.06)_50%,transparent_60%)]"></div>
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/40 to-transparent"></div>

                    <div class="relative flex flex-col">
                        <span class="text-xs font-semibold tracking-wider text-indigo-200/80 uppercase">Awaiting your approval</span>
                        <span class="mt-2 font-display text-3xl leading-none font-extrabold tracking-tight text-white tabular-nums lg:text-[2.5rem]">{{ $peso($summary['amount']) }}</span>
                        <span class="mt-2 text-sm font-medium text-indigo-100/70">{{ $summary['count'] }} requests</span>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 lg:mt-7 lg:border-t lg:border-white/10 lg:pt-5">
                            <div class="flex items-center gap-2">
                                <span class="flex items-center gap-1.5 rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-medium text-rose-200 ring-1 ring-rose-400/30">
                                    <span class="size-1.5 rounded-full bg-rose-400"></span>
                                    {{ $summary['overdue'] }} overdue
                                </span>
                                <span class="flex items-center gap-1.5 rounded-full bg-amber-400/15 px-2.5 py-1 text-xs font-medium text-amber-200 ring-1 ring-amber-300/30">
                                    <span class="size-1.5 rounded-full bg-amber-300"></span>
                                    {{ $summary['dueToday'] }} due today
                                </span>
                            </div>
                            <a href="{{ Approvals::getUrl() }}" class="flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm transition hover:bg-indigo-50 active:scale-95 lg:px-5 lg:py-2.5 lg:text-sm">
                                Review now
                                <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Needs your action --}}
                <div class="mt-2 flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-display text-base font-bold tracking-tight text-slate-900 lg:text-lg">Needs your action</h2>
                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ $dueSoon }} due soon</span>
                    </div>
                    <a href="{{ Approvals::getUrl() }}" class="text-xs font-semibold text-indigo-600 hover:underline lg:text-sm">See all ({{ $summary['count'] }})</a>
                </div>

                <div class="flex flex-col gap-3">
                    @foreach ($approvals as $approval)
                        <article class="my-glass flex flex-col gap-3 rounded-2xl p-4 transition hover:shadow-md lg:gap-4 lg:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2.5 lg:gap-3.5">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full shadow-sm lg:size-11 lg:rounded-xl {{ $iconTones[$approval['tone']] }}">
                                        <x-filament::icon :icon="$approval['icon']" class="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <a href="{{ $detailUrl($approval) }}" class="truncate text-sm font-bold text-slate-900 hover:text-indigo-700 lg:font-display lg:text-base">{{ $approval['payee'] }}</a>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $approval['tag'] }}</span>
                                        </div>
                                        <span class="font-mono text-[11px] text-slate-400">{{ $approval['number'] }}</span>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="font-display text-sm font-bold text-slate-900 tabular-nums lg:text-lg">{{ $peso($approval['amount']) }}</span>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $pillTones[$approval['dueTone']] }}">{{ $approval['due'] }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-600">
                                        {{ str($approval['requestor'])->explode(' ')->map(fn (string $part): string => $part[0])->take(2)->join('') }}
                                    </span>
                                    <span class="truncate text-xs text-slate-600">{{ $approval['requestor'] }}</span>
                                </div>

                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span class="flex items-center gap-1">
                                        @for ($step = 1; $step <= $approval['approvers']; $step++)
                                            <span @class(['size-1.5 rounded-full lg:size-2', 'bg-indigo-600' => $step <= $approval['approver'], 'bg-slate-200' => $step > $approval['approver']])></span>
                                        @endfor
                                    </span>
                                    <span class="text-[11px] font-medium text-slate-400">
                                        Step {{ $approval['approver'] }} of {{ $approval['approvers'] }}
                                        <span class="hidden lg:inline">· Approval matrix</span>
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-slate-100/70 pt-3">
                                <a href="{{ $detailUrl($approval) }}" class="rounded-xl border border-slate-200 bg-white/80 px-3.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 active:scale-95 lg:px-4 lg:py-2 lg:text-sm">
                                    Review
                                </a>
                                <a href="{{ RfpDetail::getUrl(['number' => $approval['number'], 'decide' => 'approve']) }}" class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800 active:scale-95 lg:px-5 lg:py-2 lg:text-sm">
                                    <x-filament::icon :icon="Heroicon::Check" class="hidden size-4 lg:block" />
                                    Approve
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- My requests --}}
        <section @class([
            'flex flex-col gap-3 lg:my-glass lg:rounded-2xl lg:p-6',
            'lg:sticky lg:top-8 lg:col-span-5' => $isApprover,
        ])>
            <div class="flex items-center justify-between px-1 lg:px-0">
                <h2 class="font-display text-base font-bold tracking-tight text-slate-900 lg:text-lg">My requests</h2>
                <a href="{{ MyRequests::getUrl() }}" class="text-xs font-semibold text-indigo-600 hover:underline lg:text-sm">See all ({{ $requests['total'] }})</a>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto py-0.5 lg:grid lg:grid-cols-3 lg:gap-2.5">
                <a href="{{ MyRequests::getUrl(['tab' => 'drafts']) }}" class="my-glass flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 lg:flex-col lg:gap-0.5 lg:rounded-xl lg:p-3">
                    <span class="text-xs font-medium text-slate-600 lg:text-[11px] lg:tracking-wider lg:uppercase">Drafts</span>
                    <span class="rounded-full bg-slate-100 px-1.5 text-[10px] font-bold text-slate-700 lg:bg-transparent lg:font-display lg:text-lg">{{ $requests['drafts'] }}</span>
                </a>
                <a href="{{ MyRequests::getUrl(['tab' => 'in_progress']) }}" class="flex shrink-0 items-center gap-1.5 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-indigo-900 shadow-sm lg:flex-col lg:gap-0.5 lg:rounded-xl lg:p-3">
                    <span class="text-xs font-medium lg:text-[11px] lg:tracking-wider lg:uppercase">In progress</span>
                    <span class="rounded-full bg-indigo-600 px-1.5 text-[10px] font-bold text-white lg:bg-transparent lg:font-display lg:text-lg lg:text-indigo-600">{{ $requests['inProgress'] }}</span>
                </a>
                <a href="{{ MyRequests::getUrl(['tab' => 'completed']) }}" class="my-glass flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 lg:flex-col lg:gap-0.5 lg:rounded-xl lg:p-3">
                    <span class="text-xs font-medium text-slate-600 lg:text-[11px] lg:tracking-wider lg:uppercase">Completed</span>
                    <span class="rounded-full bg-emerald-100 px-1.5 text-[10px] font-bold text-emerald-800 lg:bg-transparent lg:font-display lg:text-lg lg:text-emerald-600">{{ $requests['completed'] }}</span>
                </a>
            </div>

            <div class="mt-1 flex flex-col gap-2 lg:gap-3">
                @foreach ($this->myRequests() as $request)
                    <a href="{{ $detailUrl($request) }}" class="my-glass flex items-center justify-between gap-3 rounded-xl p-3.5 transition hover:shadow-md lg:bg-white/90">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $iconTones[$request['tone']] }}">
                                <x-filament::icon :icon="$request['icon']" class="size-[18px]" />
                            </span>
                            <div class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-slate-900 lg:text-sm">{{ $request['purpose'] }}</span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="font-mono text-[11px] text-slate-400">{{ $request['number'] }}</span>
                                    <x-my.status-pill :status="$request['status']" />
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="font-display text-xs font-bold text-slate-900 tabular-nums lg:text-sm">{{ $peso($request['amount']) }}</span>
                            <span class="hidden text-[11px] text-slate-500 lg:block">{{ $request['note'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
