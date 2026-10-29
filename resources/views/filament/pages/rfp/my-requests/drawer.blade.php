{{-- Preview drawer: a row's request without leaving the list. --}}
@php
    use App\Enums\RfpApprovalStepState;
    use App\Enums\RfpStatus;
    use App\Filament\Resources\Rfps\RfpResource;
    use App\Support\Peso;

    $rfp = $this->preview;
@endphp

@teleport('body')
<div>
@if ($rfp)
    @php
        $latest = $rfp->histories->first();
        $since = $latest?->created_at ?? $rfp->updated_at;
        $foreign = collect($rfp->foreignTotals())->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))->implode(' · ');
        $dueState = $rfp->dueState();
        $steps = $rfp->approvalSteps->where('round', $rfp->approval_round)->values();
        $comments = $rfp->commentsVisibleTo(auth()->user())->with('user')->latest()->limit(2)->get();
        $attachments = $rfp->items->flatMap(fn ($item): array => (array) $item->attachments)->filter()->values();

        $now = match (true) {
            $rfp->status === RfpStatus::Draft => 'Draft, not submitted yet.',
            $rfp->status === RfpStatus::ForRevision => 'Returned to you '.$since->diffForHumans().($latest?->remarks ? ': “'.str($latest->remarks)->limit(140).'”' : '.'),
            $rfp->status->isAwaitingApproval() => 'With '.($rfp->currentApproverLabel() ?? 'the approver').' since '.$since->diffForHumans().'.',
            $rfp->status === RfpStatus::Completed => 'Payment released '.$since->format('M j, Y').'.',
            default => $rfp->status->getLabel().' '.$since->diffForHumans().($latest?->user ? ' by '.$latest->user->name : '').($latest?->remarks ? ': “'.str($latest->remarks)->limit(140).'”' : '.'),
        };

        $next = match (true) {
            $rfp->status === RfpStatus::Draft => 'Complete the lines and documents, then submit it.',
            $rfp->status === RfpStatus::ForRevision => 'Fix what was asked and resubmit.',
            $rfp->status->isAwaitingApproval() => 'Nothing to do for now. You will be notified when it moves.',
            default => 'Nothing left to do.',
        };
    @endphp

    <div
        wire:key="rfp-drawer-{{ $rfp->id }}"
        x-data="{ open: false }"
        x-init="$nextTick(() => open = true)"
        x-on:keydown.escape.window="$wire.closePreview()"
        class="fixed inset-0 z-[60]"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $rfp->rfp_number }}"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-950/30 backdrop-blur-sm" wire:click="closePreview"></div>

        <aside
            x-show="open"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            class="absolute inset-y-0 end-0 flex w-full flex-col bg-white shadow-2xl sm:w-[30rem] dark:bg-gray-900"
        >
            {{-- Header --}}
            <div class="shrink-0 border-b border-gray-100 p-6 dark:border-white/5">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-1.5 text-[0.6875rem]">
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold uppercase tracking-wider text-gray-600 dark:bg-white/10 dark:text-gray-300">Request for payment</span>
                        <span class="truncate font-bold text-gray-500">· {{ $rfp->company?->name }}</span>
                    </div>
                    <button type="button" wire:click="closePreview" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/5" aria-label="Close preview">
                        <x-filament::icon icon="heroicon-m-x-mark" class="size-5" />
                    </button>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-xl font-extrabold tracking-tight text-gray-950 dark:text-white">{{ $rfp->rfp_number }}</h2>
                    <div class="flex items-center gap-1.5">
                        <x-filament::badge :color="$rfp->status->getColor()">{{ $rfp->status->getLabel() }}</x-filament::badge>
                        @if ($rfp->dueType?->code === 'urgent')
                            <x-filament::badge color="danger">{{ $rfp->dueType->name }}</x-filament::badge>
                        @endif
                    </div>
                </div>
                <div class="mt-3 rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-display text-3xl font-bold tracking-tight tabular-nums text-gray-950 dark:text-white">{{ Peso::format((float) $rfp->total_php_amount) }}</span>
                        <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500">PHP</span>
                    </div>
                    @if ($foreign)
                        <div class="mt-0.5 text-xs tabular-nums text-gray-500">{{ $foreign }}</div>
                    @endif
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                    <span class="flex min-w-0 items-center gap-1.5 font-semibold text-gray-900 dark:text-white">
                        <x-filament::icon icon="heroicon-m-building-office-2" class="size-4 shrink-0 text-primary-600" />
                        <span class="truncate">{{ $rfp->vendor?->name ?? 'No payee yet' }}</span>
                    </span>
                    @if ($rfp->due_date)
                        <span @class(['flex shrink-0 items-center gap-1 font-medium', 'text-danger-600' => $dueState === 'overdue', 'text-warning-600' => $dueState === 'today', 'text-gray-500' => ! $dueState])>
                            <x-filament::icon icon="heroicon-m-clock" class="size-4" />
                            Due {{ $rfp->due_date->format('M j') }}@if ($dueState === 'overdue') (overdue {{ (int) $rfp->due_date->diffInDays(today()) }}d)@endif
                        </span>
                    @endif
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
                {{-- Summary --}}
                <div class="rounded-xl bg-gradient-to-br from-primary-50 via-white to-primary-50/40 p-4 ring-1 ring-primary-100 dark:from-primary-500/10 dark:via-gray-900 dark:to-gray-900 dark:ring-primary-500/20">
                    <div class="mb-3 flex items-center gap-1.5 font-display text-sm font-bold text-primary-700 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-m-sparkles" class="size-4" />
                        Summary
                    </div>
                    <ul class="space-y-2.5 text-xs text-gray-800 dark:text-gray-200">
                        <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary-500"></span><div><strong class="font-semibold">Purpose:</strong> {{ $rfp->purpose ?: '—' }}</div></li>
                        <li class="flex gap-2"><span @class(['mt-1.5 size-1.5 shrink-0 rounded-full', 'bg-danger-500' => $dueState === 'overdue', 'bg-primary-500' => $dueState !== 'overdue'])></span><div><strong class="font-semibold">Now:</strong> {{ $now }}</div></li>
                        <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-gray-400"></span><div><strong class="font-semibold">Next:</strong> {{ $next }}</div></li>
                    </ul>
                </div>

                {{-- Approval route --}}
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-display text-sm font-bold text-gray-950 dark:text-white">Approval route</h3>
                        @if ($steps->isNotEmpty())
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[0.6875rem] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $steps->count() }} steps</span>
                        @endif
                    </div>
                    @if ($rfp->submitted_at || $steps->isNotEmpty())
                        <ol class="relative space-y-4 ps-7">
                            <span class="absolute bottom-2 start-2.5 top-2 w-0.5 bg-gray-200 dark:bg-white/10"></span>
                            <li class="relative">
                                <span class="absolute -start-7 flex size-5 items-center justify-center rounded-full bg-success-600 text-white ring-4 ring-white dark:ring-gray-900"><x-filament::icon icon="heroicon-m-check" class="size-3" /></span>
                                <div class="flex items-baseline justify-between gap-2 text-xs">
                                    <span class="font-semibold text-gray-900 dark:text-white">Submitted</span>
                                    <span class="text-gray-500">{{ $rfp->submitted_at?->format('M j, g:i A') }}</span>
                                </div>
                                <p class="text-xs text-gray-500">{{ $rfp->requestor?->name }}</p>
                            </li>
                            @foreach ($steps as $step)
                                @php $isActive = $step->state === RfpApprovalStepState::Active; @endphp
                                <li @class(['relative', 'opacity-60' => in_array($step->state, [RfpApprovalStepState::Pending, RfpApprovalStepState::Superseded], true)])>
                                    <span @class([
                                        'absolute -start-7 flex size-5 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                                        'bg-success-600 text-white' => $step->state === RfpApprovalStepState::Approved,
                                        'bg-primary-600 text-white animate-pulse' => $isActive,
                                        'bg-warning-400 text-warning-950' => $step->state === RfpApprovalStepState::Returned,
                                        'bg-danger-600 text-white' => in_array($step->state, [RfpApprovalStepState::Rejected, RfpApprovalStepState::Cancelled], true),
                                        'bg-gray-200 text-gray-500 dark:bg-white/10' => in_array($step->state, [RfpApprovalStepState::Pending, RfpApprovalStepState::Skipped, RfpApprovalStepState::Superseded], true),
                                    ])>
                                        @if ($step->state === RfpApprovalStepState::Approved)
                                            <x-filament::icon icon="heroicon-m-check" class="size-3" />
                                        @elseif ($step->state === RfpApprovalStepState::Returned)
                                            <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="size-3" />
                                        @elseif (in_array($step->state, [RfpApprovalStepState::Rejected, RfpApprovalStepState::Cancelled], true))
                                            <x-filament::icon icon="heroicon-m-x-mark" class="size-3" />
                                        @else
                                            <span class="size-1.5 rounded-full bg-current"></span>
                                        @endif
                                    </span>
                                    <div @class(['rounded-xl', '-mx-2 bg-primary-50 p-2 dark:bg-primary-500/10' => $isActive])>
                                        <div class="flex items-baseline justify-between gap-2 text-xs">
                                            <span @class(['font-semibold', 'text-primary-700 dark:text-primary-300' => $isActive, 'text-gray-900 dark:text-white' => ! $isActive])>
                                                {{ $step->label ?? $step->role?->value }}
                                                @if ($isActive)
                                                    <span class="ms-1 rounded-full bg-primary-600 px-1.5 py-px text-[0.625rem] font-bold text-white">{{ (int) $since->diffInDays(now()) }}d here</span>
                                                @endif
                                            </span>
                                            <span class="shrink-0 text-gray-500">{{ $step->acted_at?->format('M j, g:i A') ?? ($isActive ? 'Now' : $step->state->name) }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            {{ $step->actor?->name ? ($step->state === RfpApprovalStepState::Approved ? 'Approved by ' : '').$step->actor->name : ($isActive && $rfp->currentApprover ? $rfp->currentApprover->name : $step->role?->value) }}
                                        </p>
                                        @if ($step->remarks)
                                            <p class="mt-0.5 text-[0.6875rem] italic text-gray-500">“{{ str($step->remarks)->limit(120) }}”</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-xs text-gray-500">The route is set when you submit the request.</p>
                    @endif
                </div>

                {{-- Line items --}}
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-display text-sm font-bold text-gray-950 dark:text-white">Line items</h3>
                        <span class="text-[0.6875rem] font-semibold text-gray-500">{{ $rfp->items->count() }} {{ str('line')->plural($rfp->items->count()) }}</span>
                    </div>
                    <div class="divide-y divide-gray-200/70 overflow-hidden rounded-xl bg-gray-50 dark:divide-white/5 dark:bg-white/5">
                        @forelse ($rfp->items as $item)
                            <div class="flex items-start justify-between gap-2 p-3">
                                <div class="min-w-0">
                                    @if ($item->invoice_number)
                                        <span class="rounded bg-white px-1.5 py-px font-mono text-[0.6875rem] font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $item->invoice_number }}</span>
                                    @endif
                                    <p class="mt-1 text-xs font-medium text-gray-900 dark:text-white">{{ $item->description ?: '—' }}</p>
                                </div>
                                <div class="shrink-0 text-end">
                                    <div class="font-display text-sm font-bold tabular-nums text-gray-950 dark:text-white">{{ Peso::format((float) $item->php_amount) }}</div>
                                    @if ($item->currency && $item->currency->code !== 'PHP')
                                        <div class="text-[0.6875rem] tabular-nums text-gray-500">{{ $item->currency->code }} {{ number_format((float) $item->amount, 2) }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="p-3 text-xs text-gray-500">No lines yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Documents --}}
                @if ($attachments->isNotEmpty())
                    <div>
                        <h3 class="mb-3 font-display text-sm font-bold text-gray-950 dark:text-white">Supporting documents</h3>
                        <div class="space-y-2">
                            @foreach ($attachments as $path)
                                <div class="flex items-center gap-2.5 rounded-xl bg-gray-50 p-2.5 dark:bg-white/5">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-danger-600 shadow-sm dark:bg-gray-800">
                                        <x-filament::icon icon="heroicon-m-document-text" class="size-5" />
                                    </span>
                                    <span class="truncate text-xs font-semibold text-gray-900 dark:text-white">{{ basename((string) $path) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Messages --}}
                <div>
                    <h3 class="mb-3 font-display text-sm font-bold text-gray-950 dark:text-white">Messages</h3>
                    @forelse ($comments as $comment)
                        <div class="mb-2 rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                            <div class="mb-1 flex items-center justify-between gap-2 text-xs">
                                <span class="font-bold text-gray-900 dark:text-white">{{ $comment->user?->name }}</span>
                                <span class="text-gray-500">{{ $comment->created_at->diffForHumans(short: true) }}</span>
                            </div>
                            <p class="text-xs leading-relaxed text-gray-700 dark:text-gray-300">{{ str($comment->plainText())->limit(200) }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">No messages yet.</p>
                    @endforelse
                    <button
                        type="button"
                        x-on:click="$dispatch('open-comments-thread', { type: @js($rfp->getMorphClass()), id: {{ $rfp->id }} }); $wire.closePreview()"
                        class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline"
                    >
                        Open conversation
                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
                    </button>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex shrink-0 items-center justify-between gap-2 border-t border-gray-100 p-4 dark:border-white/5">
                <a href="{{ RfpResource::getUrl('view', ['record' => $rfp]) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-700 ring-1 ring-primary-200 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4" />
                    Open full page
                </a>
                @if (RfpResource::canEdit($rfp))
                    <a href="{{ RfpResource::getUrl('edit', ['record' => $rfp]) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary-600/30 transition hover:bg-primary-700">
                        <x-filament::icon icon="heroicon-m-pencil-square" class="size-4" />
                        Edit request
                    </a>
                @endif
            </div>
        </aside>
    </div>
@endif
</div>
@endteleport
